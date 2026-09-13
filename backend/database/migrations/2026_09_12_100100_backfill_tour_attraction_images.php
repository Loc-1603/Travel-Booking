<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill external (Wikipedia/Wikimedia) images for tour attractions
     * seeded before TourProvinceSeeder carried image URLs. Only touches
     * rows that still have no image, so admin uploads are never overwritten.
     */
    public function up(): void
    {
        foreach ($this->images() as $name => $image) {
            DB::table('tour_attractions')
                ->where('name', $name)
                ->where(function ($q): void {
                    $q->whereNull('image')->orWhere('image', '');
                })
                ->update(['image' => $image, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach ($this->images() as $name => $image) {
            DB::table('tour_attractions')
                ->where('name', $name)
                ->where('image', $image)
                ->update(['image' => null, 'updated_at' => now()]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function images(): array
    {
        return [
            'Đèo Mã Pí Lèng' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/dc/%C4%90%C3%A8o_M%C3%A3_P%C3%AD_L%C3%A8ng_2022.jpg/1280px-%C4%90%C3%A8o_M%C3%A3_P%C3%AD_L%C3%A8ng_2022.jpg',
            'Sông Nho Quế' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c3/S%C3%B4ng_Nho_Qu%E1%BA%BF_2022_-_NKS.jpg/1280px-S%C3%B4ng_Nho_Qu%E1%BA%BF_2022_-_NKS.jpg',
            'Cột cờ Lũng Cú' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/2/20/C%E1%BB%99t_c%E1%BB%9D_L%C5%A9ng_C%C3%BA.JPG/1280px-C%E1%BB%99t_c%E1%BB%9D_L%C5%A9ng_C%C3%BA.JPG',
            'Phố cổ Đồng Văn' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/3/36/Ph%E1%BB%97_C%E1%BB%95.jpg/1280px-Ph%E1%BB%97_C%E1%BB%95.jpg',
            'Fansipan' => 'https://upload.wikimedia.org/wikipedia/commons/d/de/C%C3%A1p-treo-fansipan-17.jpg',
            'Bản Cát Cát' => 'https://upload.wikimedia.org/wikipedia/commons/6/66/B%E1%BA%A3n_C%C3%A1t_C%C3%A1t.jpg',
            'Thung lũng Mường Hoa' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/b6/An_ancient_engraved_rock_of_Sapa.JPG/1280px-An_ancient_engraved_rock_of_Sapa.JPG',
            'Phố cổ Hội An' => 'https://upload.wikimedia.org/wikipedia/commons/f/f3/PhoCoHoiAn.jpg',
            'Làng rau Trà Quế' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/2/2f/Tra_Que_Village%2C_Hoi_An_%2845491719075%29.jpg/1280px-Tra_Que_Village%2C_Hoi_An_%2845491719075%29.jpg',
            'Biển An Bàng' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/b0/2024-11-23_An_Bang_Beach_in_Hoi_An_in_November.jpg/1280px-2024-11-23_An_Bang_Beach_in_Hoi_An_in_November.jpg',
        ];
    }
};
