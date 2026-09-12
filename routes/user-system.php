<?php

use App\Http\Controllers\Admin\UserSystemController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/users-system')->middleware(['web','auth'])->name('admin.user-system.')->group(function () {
    Route::get('/users', [UserSystemController::class,'users'])->middleware('permission:users.roles.view')->name('users');
    Route::post('/users', [UserSystemController::class,'storeUser'])->middleware('permission:users.roles.create')->name('users.store');
    Route::patch('/users/{user}', [UserSystemController::class,'updateUser'])->middleware('permission:users.roles.edit')->name('users.update');
    Route::post('/users/{user}/action', [UserSystemController::class,'userAction'])->middleware('permission:users.roles.edit')->name('users.action');
    Route::post('/users/import', [UserSystemController::class,'importUsers'])->middleware('permission:users.roles.create')->name('users.import');
    Route::get('/users-export', [UserSystemController::class,'exportUsers'])->middleware('permission:users.roles.export')->name('users.export');

    Route::get('/roles', [UserSystemController::class,'roles'])->middleware('permission:users.roles.view')->name('roles');
    Route::post('/roles', [UserSystemController::class,'storeRole'])->middleware('permission:users.roles.create')->name('roles.store');
    Route::patch('/roles/{role}', [UserSystemController::class,'updateRole'])->middleware('permission:users.roles.edit')->name('roles.update');
    Route::post('/roles/{role}/clone', [UserSystemController::class,'cloneRole'])->middleware('permission:users.roles.create')->name('roles.clone');
    Route::post('/roles/{role}/toggle', [UserSystemController::class,'toggleRole'])->middleware('permission:users.roles.edit')->name('roles.toggle');
    Route::get('/roles-export', [UserSystemController::class,'exportRoles'])->middleware('permission:users.roles.export')->name('roles.export');
    Route::get('/user-roles-permissions', [UserSystemController::class,'rolesPermissions'])->middleware('permission:users.roles.view')->name('roles-permissions');

    Route::get('/role-assignments', [UserSystemController::class,'assignments'])->middleware('permission:users.roles.view')->name('assignments');
    Route::post('/role-assignments', [UserSystemController::class,'assignRole'])->middleware('permission:users.roles.edit')->name('assignments.store');
    Route::delete('/role-assignments/{user}/{role}', [UserSystemController::class,'removeRole'])->middleware('permission:users.roles.delete')->name('assignments.destroy');
    Route::get('/role-assignments-export', [UserSystemController::class,'exportAssignments'])->middleware('permission:users.roles.export')->name('assignments.export');

    Route::get('/permission-groups', [UserSystemController::class,'permissionGroups'])->middleware('permission:users.roles.view')->name('permission-groups');
    Route::post('/permission-groups', [UserSystemController::class,'storePermissionGroup'])->middleware('permission:users.roles.create')->name('permission-groups.store');
    Route::get('/permission-groups-export', [UserSystemController::class,'exportPermissionGroups'])->middleware('permission:users.roles.export')->name('permission-groups.export');
    Route::post('/permission-groups-import', [UserSystemController::class,'importPermissionGroups'])->middleware('permission:users.roles.edit')->name('permission-groups.import');
    Route::patch('/permission-groups/{group}', [UserSystemController::class,'updatePermissionGroup'])->middleware('permission:users.roles.edit')->whereNumber('group')->name('permission-groups.update');
    Route::post('/permission-groups/{group}/clone', [UserSystemController::class,'clonePermissionGroup'])->middleware('permission:users.roles.create')->whereNumber('group')->name('permission-groups.clone');
    Route::post('/permission-groups/{group}/toggle', [UserSystemController::class,'togglePermissionGroup'])->middleware('permission:users.roles.edit')->whereNumber('group')->name('permission-groups.toggle');

    Route::get('/permission-matrix', [UserSystemController::class,'permissionMatrix'])->middleware('permission:users.roles.view')->name('matrix');
    Route::post('/permission-matrix', [UserSystemController::class,'updateMatrix'])->middleware('permission:users.roles.edit')->name('matrix.update');
    Route::get('/permission-matrix-export', [UserSystemController::class,'exportMatrix'])->middleware('permission:users.roles.export')->name('matrix.export');
    Route::post('/permission-matrix-import', [UserSystemController::class,'importMatrix'])->middleware('permission:users.roles.edit')->name('matrix.import');

    Route::get('/activity-log', [UserSystemController::class,'activity'])->middleware('permission:users.roles.view')->name('activity');
    Route::get('/activity-log-export', [UserSystemController::class,'exportActivity'])->middleware('permission:users.roles.export')->name('activity.export');
});

Route::prefix('admin')->middleware(['web','auth','permission:users.roles.view'])->group(function () {
    Route::get('/resource/users', fn () => redirect()->route('admin.user-system.users'));
    Route::get('/resource/roles', fn () => redirect()->route('admin.user-system.roles'));
    Route::get('/resource/permissions', fn () => redirect()->route('admin.user-system.matrix'));
});
