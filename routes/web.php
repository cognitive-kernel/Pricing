<?php
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\PriceController;

$router->get('/',fn()=>\App\Core\Security::redirect('/prices'));
$router->get('/login',[AuthController::class,'login']);$router->post('/login',[AuthController::class,'login']);$router->post('/logout',[AuthController::class,'logout']);
$router->get('/password',[AuthController::class,'password']);$router->post('/password',[AuthController::class,'password']);
$router->get('/prices',[PriceController::class,'index']);$router->get('/prices/queue',[PriceController::class,'queue']);$router->get('/prices/create',[PriceController::class,'form']);$router->post('/prices',[PriceController::class,'save']);$router->get('/prices/{id}/edit',[PriceController::class,'form']);$router->post('/prices/{id}',[PriceController::class,'save']);$router->post('/prices/{id}/delete',[PriceController::class,'delete']);
$router->get('/admin/units',[AdminController::class,'units']);$router->post('/admin/units',[AdminController::class,'storeUnit']);
$router->get('/admin/groups',[AdminController::class,'groups']);$router->post('/admin/groups',[AdminController::class,'storeGroup']);$router->post('/admin/groups/{id}/permissions',[AdminController::class,'groupPermissions']);
$router->get('/admin/users',[AdminController::class,'users']);$router->post('/admin/users',[AdminController::class,'storeUser']);$router->post('/admin/users/{id}/groups',[AdminController::class,'userGroups']);$router->post('/admin/users/{id}/permissions',[AdminController::class,'userPermissions']);
