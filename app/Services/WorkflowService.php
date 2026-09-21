<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class WorkflowService{
 public static function start(int $productId,int $stepId,?int $userId=null):int{$s=Database::connection()->prepare("INSERT INTO price_workflow(product_id,step_id,assigned_to,status,started_at) OUTPUT INSERTED.id VALUES(:p,:s,:u,'in_progress',SYSDATETIME())");$s->execute(['p'=>$productId,'s'=>$stepId,'u'=>$userId]);return (int)$s->fetchColumn();}
 public static function complete(int $id,string $comment=''):void{$s=Database::connection()->prepare("UPDATE price_workflow SET status='completed',comment=:c,completed_at=SYSDATETIME(),updated_at=SYSDATETIME() WHERE id=:id");$s->execute(['c'=>$comment?:null,'id'=>$id]);}
 public static function history(int $productId):array{$s=Database::connection()->prepare("SELECT w.*,s.name step_name,u.name assigned_name FROM price_workflow w JOIN workflow_steps s ON s.id=w.step_id LEFT JOIN users u ON u.id=w.assigned_to WHERE w.product_id=:p ORDER BY w.created_at DESC");$s->execute(['p'=>$productId]);return $s->fetchAll();}
}