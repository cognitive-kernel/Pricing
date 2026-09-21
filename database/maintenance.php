<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;use App\Services\QueueService;use App\Services\NotificationService;
$job='maintenance';$started=microtime(true);
try{$count=QueueService::rebuild();Database::connection()->exec("INSERT INTO system_jobs(job_name,status,details) VALUES('queue_rebuild','completed',".Database::connection()->quote(json_encode(['count'=>$count,'duration'=>microtime(true)-$started])).")");echo "Queue rebuilt: {$count}\n";}catch(Throwable $e){$s=Database::connection()->prepare("INSERT INTO system_jobs(job_name,status,details) VALUES('queue_rebuild','failed',:d)");$s->execute(['d'=>$e->getMessage()]);fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}