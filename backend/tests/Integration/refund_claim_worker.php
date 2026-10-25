<?php

use App\DTOs\VnpayRefundData;
use App\DTOs\VnpayRefundResult;
use App\Enums\RefundStatus;
use App\Services\RefundService;
use App\Services\VnpayRefundClient;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$refundId, $startAt, $countFile] = array_slice($argv, 1);

$app['config']->set('services.vnpay.refund_enabled', true);
$app['config']->set('services.vnpay.refund_max_attempts', 5);

$app->instance(VnpayRefundClient::class, new class($countFile) extends VnpayRefundClient
{
    public function __construct(private string $countFile) {}

    public function refund(VnpayRefundData $data): VnpayRefundResult
    {
        file_put_contents($this->countFile, $data->requestId.PHP_EOL, FILE_APPEND | LOCK_EX);

        usleep(300_000);

        return new VnpayRefundResult(
            verified: true,
            status: RefundStatus::PROCESSING,
            retryable: false,
            responseCode: '00',
            transactionStatus: '05',
            message: 'Success',
            refundTransactionNo: '15713768',
            payDate: '20261024120500',
            providerResponse: ['vnp_ResponseCode' => '00'],
        );
    }
});

$waitUntil = (float) $startAt;
while (microtime(true) < $waitUntil) {
    usleep(500);
}

try {
    $app->make(RefundService::class)->process((int) $refundId);

    fwrite(STDOUT, json_encode(['refund_id' => (int) $refundId]));

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage());
    exit(1);
}
