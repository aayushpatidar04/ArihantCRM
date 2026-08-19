<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Executive\CustomerController as ExecutiveCustomerController;
use App\Http\Controllers\Executive\MessageController as ExecutiveMessageController;
use App\Http\Controllers\SuperAdmin\MetaWhatsappSettingController;
use App\Http\Controllers\SuperAdmin\TeamController;
use App\Http\Controllers\SuperAdmin\TemplateController;
use App\Http\Controllers\SuperAdmin\WhatsAppNumberController;
use App\Http\Controllers\SuperAdmin\WhatsAppTemplateController;
use App\Http\Controllers\TeamAdmin\CustomerController;
use App\Http\Controllers\TeamAdmin\MessageController;
use App\Http\Controllers\TeamAdmin\UserController;
use App\Http\Controllers\TeamAdmin\WorkspaceController;
use App\Http\Controllers\Webhook\MetaWhatsappWebhookController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => false,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:super_admin')
        ->prefix('superadmin')
        ->name('superadmin.')
        ->group(function () {
            Route::resource('teams', TeamController::class);

            Route::post('/teams/{team}/admins', [TeamController::class, 'storeAdmin'])->name('teams.admins.store');
            Route::put('/teams/{team}/admins/{user}', [TeamController::class, 'updateAdmin'])->name('teams.admins.update');
            Route::delete('/teams/{team}/admins/{user}', [TeamController::class, 'destroyAdmin'])->name('teams.admins.destroy');
            Route::get('/teams/{team}/available-admins', [TeamController::class, 'availableAdmins'])->name('teams.available-admins');

            Route::post('/teams/sync-bitrix', [TeamController::class, 'syncBitrix'])->name('teams.sync-bitrix');

            Route::prefix('meta-whatsapp-settings')
                ->name('meta-whatsapp-settings.')
                ->group(function () {
                    Route::get('/', [MetaWhatsappSettingController::class, 'index'])->name('index');
                    Route::get('/create', [MetaWhatsappSettingController::class, 'create'])->name('create');
                    Route::post('/', [MetaWhatsappSettingController::class, 'store'])->name('store');
                    Route::get('/{metaWhatsappSetting}/edit', [MetaWhatsappSettingController::class, 'edit'])->name('edit');
                    Route::put('/{metaWhatsappSetting}', [MetaWhatsappSettingController::class, 'update'])->name('update');
                    Route::delete('/{metaWhatsappSetting}', [MetaWhatsappSettingController::class, 'destroy'])->name('destroy');
                    Route::get('/{metaWhatsappSetting}', [MetaWhatsappSettingController::class, 'show'])->name('show');
                });


            Route::prefix('whatsapp-numbers')
                ->name('whatsapp-numbers.')
                ->group(function () {
                    Route::get('/', [WhatsAppNumberController::class, 'index'])->name('index');
                    Route::get('/create', [WhatsAppNumberController::class, 'create'])->name('create');
                    Route::post('/', [WhatsAppNumberController::class, 'store'])->name('store');
                    Route::get('/{whatsappNumber}', [WhatsAppNumberController::class, 'show'])->name('show');
                    Route::get('/{whatsappNumber}/edit', [WhatsAppNumberController::class, 'edit'])->name('edit');
                    Route::put('/{whatsappNumber}', [WhatsAppNumberController::class, 'update'])->name('update');
                    Route::delete('/{whatsappNumber}', [WhatsAppNumberController::class, 'destroy'])->name('destroy');
                    Route::patch('/{whatsappNumber}/toggle', [WhatsAppNumberController::class, 'toggleActive'])->name('toggle');
                    Route::post('/{whatsappNumber}/test-connection', [WhatsAppNumberController::class, 'testConnection'])->name('test-connection');
                    Route::post('/{whatsappNumber}/sync-templates', [WhatsAppTemplateController::class, 'sync'])->name('sync-templates');
                });

            Route::prefix('whatsapp-templates')
                ->name('whatsapp-templates.')
                ->group(function () {

                    Route::get('/', [WhatsAppTemplateController::class, 'index'])->name('index');
                    Route::get('/{whatsappTemplate}', [WhatsAppTemplateController::class, 'show'])->name('show');
                    Route::get('/{whatsappTemplate}/edit', [WhatsAppTemplateController::class, 'edit'])->name('edit');
                    Route::put('/{whatsappTemplate}', [WhatsAppTemplateController::class, 'update'])->name('update');
                    Route::post('/{whatsappTemplate}/toggle', [WhatsAppTemplateController::class, 'toggle'])->name('toggle');
                    Route::post('/{whatsappNumber}/templates/{whatsappTemplate}/send-test', [WhatsAppTemplateController::class, 'sendTest'])->name('send-test');
                });

        });

    Route::middleware(['role:team_admin', 'team.workspace'])
        ->prefix('team-admin')
        ->name('team-admin.')
        ->group(function () {

            Route::post('/workspace/switch', [WorkspaceController::class, 'switch'])->name('workspace.switch');
            Route::get('/dashboard/unread-messages', [DashboardController::class, 'unreadMessages'])->name('dashboard.unread-messages');

            Route::prefix('users')
                ->name('users.')
                ->group(function () {
                    Route::get('/', [UserController::class, 'index'])
                        ->name('index');

                    Route::get('/create', [UserController::class, 'create'])
                        ->name('create');

                    Route::post('/', [UserController::class, 'store'])
                        ->name('store');

                    Route::get('/{user}/edit', [UserController::class, 'edit'])
                        ->name('edit');

                    Route::put('/{user}', [UserController::class, 'update'])
                        ->name('update');

                    Route::delete('/{user}', [UserController::class, 'destroy'])
                        ->name('destroy');
                });

            Route::resource('customers', CustomerController::class)->names('customers');
            Route::post('/customers/{customer}/assign', [CustomerController::class, 'assign'])->name('customers.assign');

            Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
            Route::get('/messages/{customer}', [MessageController::class, 'show'])->name('messages.show');
            Route::post('/messages/{customer}/send-text', [MessageController::class, 'sendText'])->name('messages.send-text');
            Route::post('/messages/{customer}/send-media', [MessageController::class, 'sendMedia'])->name('messages.send-media');
            Route::post('/messages/{customer}/send-template', [MessageController::class, 'sendTemplate'])->name('messages.send-template');
            Route::post('/messages/{customer}/mark-read', [MessageController::class, 'markRead'])->name('messages.mark-read');
            Route::get('/messages/{customer}/history', [MessageController::class, 'history'])->name('messages.history');

        });

    Route::middleware(['role:executive'])
        ->prefix('executive')
        ->name('executive.')
        ->group(function () {

            Route::get('/customers', [ExecutiveCustomerController::class,'index'])->name('customers.index');
            Route::get('/customers/create', [ExecutiveCustomerController::class,'create'])->name('customers.create');
            Route::post('/customers', [ExecutiveCustomerController::class,'store'])->name('customers.store');
            Route::get('/customers/{customer}', [ExecutiveCustomerController::class,'show'])->name('customers.show');
            Route::put('/customers/{customer}', [ExecutiveCustomerController::class,'update'])->name('customers.update');

            Route::get('/messages/unread', [ExecutiveMessageController::class,'unreadMessages'])->name('messages.unread');

            Route::get('/messages', [ExecutiveMessageController::class,'index'])->name('messages.index');
            Route::get('/messages/{customer}', [ExecutiveMessageController::class,'show'])->name('messages.show');
            Route::post('/messages/{customer}/send', [ExecutiveMessageController::class,'send'])->name('messages.send');
            Route::post('/messages/{customer}/media', [ExecutiveMessageController::class,'sendMedia'])->name('messages.send-media');
            Route::post('/messages/{customer}/template', [ExecutiveMessageController::class,'sendTemplate'])->name('messages.send-template');
            Route::post('/messages/{customer}/read', [ExecutiveMessageController::class,'markRead'])->name('messages.mark-read');
            Route::get('/messages/{customer}/history', [ExecutiveMessageController::class, 'history'])->name('messages.history');

        });

    Route::post('/customers/fetch-bitrix-lead', [DashboardController::class, 'fetchBitrixLead'])->name('customers.fetch-bitrix-lead');
});

Route::get('/webhooks/meta/whatsapp', [MetaWhatsappWebhookController::class, 'verify'])->name('webhooks.meta.whatsapp.verify');
Route::post('/webhooks/meta/whatsapp', [MetaWhatsappWebhookController::class, 'handle'])->name('webhooks.meta.whatsapp.handle');

require __DIR__ . '/auth.php';
