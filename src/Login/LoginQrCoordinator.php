<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\QrAuthCode;

final class LoginQrCoordinator
{
    public function __construct(
        private readonly LoginQrService $qrService,
        private readonly LoginPromptService $promptService,
    ) {
    }

    public function fetchAuthCode(): QrAuthCode
    {
        return $this->qrService->fetchAuthCode();
    }

    /**
     * @param callable(array<string, mixed>):void $applyLoginResponse
     * @return array{confirmed: bool, message: string}
     */
    public function pollAuthCode(string $authCode, callable $applyLoginResponse): array
    {
        $result = $this->qrService->pollAuthCode($authCode);
        if ($result->confirmed) {
            $applyLoginResponse($result->response);
        }

        return [
            'confirmed' => $result->confirmed,
            'message' => $result->message,
        ];
    }

    /**
     * @return array{mode:string,url:string}
     */
    public function resolveDisplay(string $option, string $qrUrl): array
    {
        return $this->promptService->resolveQrcodeDisplay($option, $qrUrl);
    }
}
