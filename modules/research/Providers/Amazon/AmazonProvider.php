<?php
declare(strict_types=1);

namespace AiScaler\Research\Providers\Amazon;

use AiScaler\Research\Providers\AbstractResearchProvider;

final class AmazonProvider extends AbstractResearchProvider
{
    protected function providerId(): string
    {
        return 'amazon';
    }

    protected function providerLabel(): string
    {
        return $this->stringConfig('label', 'Amazon');
    }

    public function analyze(string $idea, int $limit = 10): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse();
        }

        if (
            !$this->hasValidConfigValue('access_key')
            || !$this->hasValidConfigValue('secret_key')
            || !$this->hasValidConfigValue('partner_tag')
        ) {
            return $this->pendingResponse(
                'Completa amazon.access_key, amazon.secret_key y amazon.partner_tag en config/research.php para conectar Amazon desde PHP.'
            );
        }

        return $this->pendingResponse(
            'La carpeta del proveedor Amazon ya quedo aislada y lista para evolucionar, pero la firma segura de la API se dejara en una siguiente iteracion para no mezclarla con el resto del modulo.'
        );
    }
}
