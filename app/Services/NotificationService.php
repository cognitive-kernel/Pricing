<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class NotificationService{
 public static function create(?int $userId,string $type,string $title,string $message):void{
  $s=Database::connection()->prepare('INSERT INTO notifications(user_id,type,title,message) VALUES(:u,:t,:ti,:m)');$s->execute(['u'=>$userId,'t'=>$type,'ti'=>$title,'m'=>$message]);
 }
 public static function list(?int $userId):array{
  $s=Database::connection()->prepare('SELECT TOP 100 * FROM notifications WHERE user_id=:u OR user_id IS NULL ORDER BY created_at DESC');$s->execute(['u'=>$userId]);return $s->fetchAll();
 }
 public static function markRead(int $id,int $userId):void{
  $s=Database::connection()->prepare('UPDATE notifications SET is_read=1,read_at=SYSDATETIME() WHERE id=:id AND (user_id=:u OR user_id IS NULL)');$s->execute(['id'=>$id,'u'=>$userId]);
 }
}