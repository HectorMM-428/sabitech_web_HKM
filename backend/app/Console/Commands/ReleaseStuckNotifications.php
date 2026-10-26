<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

/** Lo ejecuta el planificador cada 5 minutos (routes/console.php). */
class ReleaseStuckNotifications extends Command
{
    protected $signature = 'sabitech:release-stuck-notifications';

    protected $description = 'Marca como fallidos los correos atascados en «enviando» para poder reintentarlos';

    public function handle(NotificationService $notifications): int
    {
        $count = $notifications->releaseStuck();
        $this->info("Notificaciones liberadas: {$count}");

        return self::SUCCESS;
    }
}
