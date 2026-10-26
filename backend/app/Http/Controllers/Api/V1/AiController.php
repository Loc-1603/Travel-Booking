<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PlatformSetting;
use App\Services\AiBookingService;
use App\Services\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends BaseApiController
{
    public function __construct(
        protected AiBookingService $aiBookingService,
        protected GeminiClient $gemini
    ) {}

    /**
     * Personalized + popular hotel picks (optional auth for personalization).
     */
    public function recommendations(Request $request): JsonResponse
    {
        $payload = $this->aiBookingService->recommendations($request->user(), $request);

        return $this->success($payload);
    }

    /**
     * AI chat assistant for booking help (Gemini when configured).
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:24'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:6000'],
        ]);

        if (! $this->gemini->isConfigured()) {
            return $this->error('Trợ lý AI chưa được cấu hình. Vui lòng thiết lập GEMINI_API_KEY trên máy chủ.', 503, 'AI_DISABLED');
        }

        $site = PlatformSetting::get('site_name', config('app.name'));
        $system = "Bạn là trợ lý hữu ích của {$site}, một trang web đặt phòng khách sạn. ".
            'Luôn trả lời bằng tiếng Việt, kể cả khi người dùng viết bằng ngôn ngữ khác. '.
            'Hỗ trợ tìm kiếm khách sạn, hướng dẫn đặt phòng, giải thích chung về chính sách hủy và cách sử dụng trang web. '.
            'Trả lời ngắn gọn, dưới 180 từ, bằng văn bản thuần, không dùng Markdown hoặc dấu sao để định dạng. '.
            'Không tự bịa tên khách sạn, giá hoặc tình trạng phòng; '.
            'hãy hướng dẫn người dùng xem kết quả tìm kiếm trực tiếp trên trang web để biết giá và ngày còn phòng hiện tại.';

        $trimmed = array_slice($validated['messages'], -16);
        $messages = array_merge(
            [['role' => 'system', 'content' => $system]],
            array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $trimmed)
        );

        $reply = $this->gemini->chatCompletion($messages, 0.65);
        if ($reply === null) {
            return $this->error('Trợ lý hiện tạm thời chưa khả dụng. Vui lòng thử lại sau.', 503, 'AI_UNAVAILABLE');
        }

        $reply = preg_replace('/\*\*(.*?)\*\*/s', '$1', $reply) ?? $reply;
        $reply = preg_replace('/(?<!\*)\*([^*\r\n]+)\*(?!\*)/', '$1', $reply) ?? $reply;
        $reply = preg_replace('/^\s*\*\s+/m', '- ', $reply) ?? $reply;

        return $this->success([
            'message' => [
                'role' => 'assistant',
                'content' => $reply,
            ],
            'ai_enabled' => true,
        ]);
    }

    /**
     * Summarized sentiment for verified reviews (cached per hotel).
     */
    public function reviewSentiment(Request $request, int $id): JsonResponse
    {
        $data = $this->aiBookingService->reviewSentiment($id, $request);
        if ($data === null) {
            return $this->error('Hotel not found.', 404, 'NOT_FOUND');
        }

        return $this->success($data);
    }
}
