<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gộp tour_reviews (+ tour_review_images) vào bảng reviews duy nhất.
 *
 * - reviews.booking_id: nullable (review hotel), giữ FK bookings.
 * - reviews.tour_booking_id: mới, nullable (review tour/guide), FK tour_bookings.
 * - Ảnh review chuyển sang bảng review_images dùng chung.
 * - Bỏ cột approved: review hiển thị ngay, admin chỉ hậu kiểm qua hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Bỏ unique booking_id để chuyển sang nullable + unique(booking_id, tour_booking_id riêng).
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['booking_id']);
        });

        // 2) booking_id cho phép NULL (review tour không có hotel booking).
        Schema::table('reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });

        // 3) Cột liên kết tour booking + ràng buộc.
        Schema::table('reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('tour_booking_id')->nullable()->after('booking_id');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->unique('booking_id');
            $table->unique('tour_booking_id');
            $table->foreign('tour_booking_id')->references('id')->on('tour_bookings')->cascadeOnDelete();
            $table->index('hidden');
        });

        // 4) Bỏ cột approved (không cần duyệt trước).
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex(['approved']);
            $table->dropColumn('approved');
        });

        // 5) Bảng ảnh review dùng chung.
        Schema::create('review_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('path', 500);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 6) Chuyển dữ liệu tour_reviews -> reviews (kèm ảnh).
        if (Schema::hasTable('tour_reviews')) {
            DB::table('tour_reviews')->orderBy('id')->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $reviewId = DB::table('reviews')->insertGetId([
                        'booking_id' => null,
                        'tour_booking_id' => $row->tour_booking_id,
                        'rating' => $row->rating,
                        'comment' => $row->comment,
                        'hidden' => (bool) $row->hidden,
                        'moderated_at' => $row->moderated_at,
                        'moderated_by' => $row->moderated_by,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);

                    if (! Schema::hasTable('tour_review_images')) {
                        continue;
                    }

                    $images = DB::table('tour_review_images')
                        ->where('tour_review_id', $row->id)
                        ->orderBy('sort_order')
                        ->get();

                    foreach ($images as $image) {
                        DB::table('review_images')->insert([
                            'review_id' => $reviewId,
                            'path' => $image->path,
                            'sort_order' => $image->sort_order,
                            'created_at' => $image->created_at,
                            'updated_at' => $image->updated_at,
                        ]);
                    }
                }
            });
        }

        // 7) Bỏ bảng cũ.
        Schema::dropIfExists('tour_review_images');
        Schema::dropIfExists('tour_reviews');
    }

    public function down(): void
    {
        // Khôi phục bảng tour_reviews + ảnh riêng.
        Schema::create('tour_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_booking_id')->constrained('tour_bookings')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('approved')->default(false);
            $table->boolean('hidden')->default(false);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('tour_booking_id');
            $table->index('approved');
        });

        Schema::create('tour_review_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_review_id')->constrained('tour_reviews')->cascadeOnDelete();
            $table->string('path', 500);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('reviews')->whereNotNull('tour_booking_id')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $legacyId = DB::table('tour_reviews')->insertGetId([
                    'tour_booking_id' => $row->tour_booking_id,
                    'rating' => $row->rating,
                    'comment' => $row->comment,
                    'approved' => ! (bool) $row->hidden,
                    'hidden' => (bool) $row->hidden,
                    'moderated_at' => $row->moderated_at,
                    'moderated_by' => $row->moderated_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                if (! Schema::hasTable('review_images')) {
                    continue;
                }

                $images = DB::table('review_images')->where('review_id', $row->id)->orderBy('sort_order')->get();
                foreach ($images as $image) {
                    DB::table('tour_review_images')->insert([
                        'tour_review_id' => $legacyId,
                        'path' => $image->path,
                        'sort_order' => $image->sort_order,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                    ]);
                }
            }
        });

        Schema::dropIfExists('review_images');

        DB::table('reviews')->whereNull('booking_id')->delete();

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['tour_booking_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->dropForeign(['tour_booking_id']);
            });
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('tour_booking_id');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->boolean('approved')->default(false)->after('comment');
            $table->index('approved');
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
        });
    }
};
