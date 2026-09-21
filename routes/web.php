<?php
use App\Controllers\AdminController;use App\Controllers\AuthController;use App\Controllers\PriceController;use App\Controllers\ManagementController;
$router->get('/',fn()=>\App\Core\Security::redirect('/prices'));
$router->get('/login',[AuthController::class,'login']);$router->post('/login',[AuthController::class,'login']);$router->post('/logout',[AuthController::class,'logout']);
$router->get('/password',[AuthController::class,'password']);$router->post('/password',[AuthController::class,'password']);
$router->get('/prices',[PriceController::class,'index']);$router->get('/prices/queue',[PriceController::class,'queue']);$router->get('/prices/create',[PriceController::class,'form']);$router->post('/prices',[PriceController::class,'save']);$router->get('/prices/{id}/edit',[PriceController::class,'form']);$router->post('/prices/{id}',[PriceController::class,'save']);$router->post('/prices/{id}/delete',[PriceController::class,'delete']);
$router->get('/admin/units',[AdminController::class,'units']);$router->post('/admin/units',[AdminController::class,'storeUnit']);
$router->get('/admin/groups',[AdminController::class,'groups']);$router->post('/admin/groups',[AdminController::class,'storeGroup']);$router->post('/admin/groups/{id}/permissions',[AdminController::class,'groupPermissions']);
$router->get('/admin/users',[AdminController::class,'users']);$router->post('/admin/users',[AdminController::class,'storeUser']);$router->post('/admin/users/{id}/groups',[AdminController::class,'userGroups']);$router->post('/admin/users/{id}/permissions',[AdminController::class,'userPermissions']);
$router->get('/dashboard',[ManagementController::class,'dashboard']);$router->post('/queue/rebuild',[ManagementController::class,'rebuildQueue']);
$router->get('/admin/suppliers',[ManagementController::class,'suppliers']);$router->post('/admin/suppliers',[ManagementController::class,'storeSupplier']);
$router->get('/notifications',[ManagementController::class,'notifications']);$router->get('/notifications/{id}/read',[ManagementController::class,'readNotification']);
$router->get('/audit',[ManagementController::class,'audit']);$router->get('/reports',[ManagementController::class,'reports']);$router->get('/reports/export',[ManagementController::class,'exportPrices']);
$router->get('/settings',[ManagementController::class,'settings']);$router->post('/settings',[ManagementController::class,'saveSetting']);
$router->get('/imports/products',[ManagementController::class,'importForm']);$router->post('/imports/products',[ManagementController::class,'importProducts']);