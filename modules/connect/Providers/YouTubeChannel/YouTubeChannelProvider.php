<?php
declare(strict_types=1);

namespace AiScaler\Connect\Providers\YouTubeChannel;

use AiScaler\Connect\Providers\AbstractConnectionProvider;

final class YouTubeChannelProvider extends AbstractConnectionProvider
{
    public function definition(): array
    {
        return $this->buildDefinition([
            'key' => 'youtube_channel',
            'platform' => 'YouTube',
            'label' => 'Canal de YouTube',
            'icon' => 'smart_display',
            'description' => 'Registra el canal exacto que deseas administrar y analizar.',
            'helper' => 'Cada usuario puede registrar tantos canales como necesite, incluso 5 o mas.',
            'handle_label' => 'Handle del canal',
            'external_id_label' => 'Channel ID',
            'url_label' => 'URL del canal',
        ]);
    }
}
