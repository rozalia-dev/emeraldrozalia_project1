<?php

use App\Http\Controllers\Admin\UserSystemController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/users-system')->middleware(['web','auth','admin'])->name('admin.user-system.')->group(function () {
    Route::get('/users', [UserSystemController::class,'users'])->name('users');
    Route::post('/users', [UserSystemController::class,'storeUser'])->name('users.store');
    Route::patch('/users/{user}', [UserSystemController::class,'updateUser'])->name('users.update');
    Route::post('/users/{user}/action', [UserSystemController::class,'userAction'])->name('users.action');
    Route::post('/users/import', [UserSystemController::class,'importUsers'])->name('users.import');
    Route::get('/users-export', [UserSystemController::class,'exportUsers'])->name('users.export');

    Route::get('/roles', [UserSystemController::class,'roles'])->name('roles');
    Route::post('/roles', [UserSystemController::class,'storeRole'])->name('roles.store');
    Route::patch('/roles/{role}', [UserSystemController::class,'updateRole'])->name('roles.update');
    Route::post('/roles/{role}/clone', [UserSystemController::class,'cloneRole'])->name('roles.clone');
    Route::post('/roles/{role}/toggle', [UserSystemController::class,'toggleRole'])->name('roles.toggle');
    Route::get('/roles-export', [UserSystemController::class,'exportRoles'])->name('roles.export');
    Route::get('/user-roles-permissions', [UserSystemController::class,'rolesPermissions'])->name('roles-permissions');

    Route::get('/role-assignments', [UserSystemController::class,'assignments'])->name('assignments');
    Route::post('/role-assignments', [UserSystemController::class,'assignRole'])->name('assignments.store');
    Route::delete('/role-assignments/{user}/{role}', [UserSystemController::class,'removeRole'])->name('assignments.destroy');
    Route::get('/role-assignments-export', [UserSystemController::class,'exportAssignments'])->name('assignments.export');

    Route::get('/permission-groups', [UserSystemController::class,'permissionGroups'])->name('permission-groups');
    Route::post('/permission-groups', [UserSystemController::class,'storePermissionGroup'])->name('permission-groups.store');
    Route::get('/permission-groups-export', [UserSystemController::class,'exportPermissionGroups'])->name('permission-groups.export');
    Route::post('/permission-groups-import', [UserSystemController::class,'importPermissionGroups'])->name('permission-groups.import');
    Route::patch('/permission-groups/{group}', [UserSystemController::class,'updatePermissionGroup'])->whereNumber('group')->name('permission-groups.update');
    Route::post('/permission-groups/{group}/clone', [UserSystemController::class,'clonePermissionGroup'])->whereNumber('group')->name('permission-groups.clone');
    Route::post('/permission-groups/{group}/toggle', [UserSystemController::class,'togglePermissionGroup'])->whereNumber('group')->name('permission-groups.toggle');

    Route::get('/permission-matrix', [UserSystemController::class,'permissionMatrix'])->name('matrix');
    Route::post('/permission-matrix', [UserSystemController::class,'updateMatrix'])->name('matrix.update');
    Route::get('/permission-matrix-export', [UserSystemController::class,'exportMatrix'])->name('matrix.export');
    Route::post('/permission-matrix-import', [UserSystemController::class,'importMatrix'])->name('matrix.import');

    Route::get('/activity-log', [UserSystemController::class,'activity'])->name('activity');
    Route::get('/activity-log-export', [UserSystemController::class,'exportActivity'])->name('activity.export');
});

Route::prefix('admin')->middleware(['web','auth','admin'])->group(function () {
    Route::get('/resource/users', fn () => redirect()->route('admin.user-system.users'));
    Route::get('/resource/roles', fn () => redirect()->route('admin.user-system.roles'));
    Route::get('/resource/permissions', fn () => redirect()->route('admin.user-system.matrix'));
});
