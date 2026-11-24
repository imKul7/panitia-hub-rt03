<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{PublicController,AuthController,DashboardController,PeopleController,TaskController,NoteController,FinanceController,CompetitionController,ScheduleController,AnnouncementController,MentorController,DocumentController,GuideController,ProfileController,PrizeController,InventoryController,GalleryController,PeriodController};

Route::get('/',[PublicController::class,'home'])->name('public.home');
Route::get('/galeri',[PublicController::class,'gallery'])->name('public.gallery');
Route::get('/foto/{item}',[GalleryController::class,'photo'])->name('gallery.photo');
Route::get('/informasi/{section}',[PublicController::class,'section'])->where('section','lomba|jadwal|pengumuman|hasil')->name('public.section');

Route::middleware('guest')->group(function() {
    Route::get('/login',[AuthController::class,'show'])->name('login');
    Route::post('/login',[AuthController::class,'login'])->middleware('throttle:6,1')->name('login.submit');
});
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('app')->group(function() {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::delete('/records/{resource}/{id}', \App\Http\Controllers\RecordDeletionController::class)->whereNumber('id')->name('records.destroy');
    Route::get('/profil',[ProfileController::class,'index'])->name('profile.index');
    Route::patch('/profil',[ProfileController::class,'update'])->name('profile.update');
    Route::patch('/profil/password',[ProfileController::class,'changePassword'])->name('profile.password');
    Route::get('/profil/foto',[ProfileController::class,'photo'])->name('profile.photo');
    Route::post('/profil/foto',[ProfileController::class,'storePhoto'])->middleware('throttle:12,1')->name('profile.photo.store');
    Route::delete('/profil/foto',[ProfileController::class,'deletePhoto'])->name('profile.photo.destroy');
    Route::get('/panitia',[PeopleController::class,'index'])->name('people.index');
    Route::post('/panitia',[PeopleController::class,'store'])->name('people.store');
    Route::post('/panitia/penugasan',[PeopleController::class,'assign'])->name('people.assign');
    Route::patch('/panitia/akun/{user}/reset-password',[\App\Http\Controllers\AdminPasswordController::class,'update'])
        ->middleware('throttle:10,1')->name('people.password.reset');
    Route::get('/tugas',[TaskController::class,'index'])->name('tasks.index');
    Route::post('/tugas',[TaskController::class,'store'])->name('tasks.store');
    Route::patch('/tugas/{task}',[TaskController::class,'update'])->name('tasks.update');
    Route::get('/catatan',[NoteController::class,'index'])->name('notes.index');
    Route::post('/catatan',[NoteController::class,'store'])->name('notes.store');
    Route::get('/keuangan',[FinanceController::class,'index'])->name('finance.index');
    Route::post('/keuangan',[FinanceController::class,'store'])->name('finance.store');
    Route::get('/pengajuan-dana',[FinanceController::class,'requests'])->name('expenses.index');
    Route::post('/pengajuan-dana',[FinanceController::class,'requestExpense'])->name('expenses.store');
    Route::patch('/pengajuan-dana/{expenseRequest}',[FinanceController::class,'review'])->name('expenses.review');
    Route::get('/keuangan/bukti/{transaction}',[FinanceController::class,'proof'])->name('finance.proof');
    Route::get('/lomba',[CompetitionController::class,'index'])->name('competitions.index');
    Route::post('/lomba',[CompetitionController::class,'store'])->name('competitions.store');
    Route::get('/peserta',[CompetitionController::class,'registrations'])->name('registrations.index');
    Route::post('/peserta',[CompetitionController::class,'register'])->name('registrations.store');
    Route::get('/hasil',[CompetitionController::class,'results'])->name('results.index');
    Route::post('/hasil',[CompetitionController::class,'storeResult'])->name('results.store');
    Route::get('/jadwal',[ScheduleController::class,'index'])->name('schedules.index');
    Route::post('/jadwal',[ScheduleController::class,'store'])->name('schedules.store');
    Route::get('/pengumuman',[AnnouncementController::class,'index'])->name('announcements.index');
    Route::post('/pengumuman',[AnnouncementController::class,'store'])->name('announcements.store');
    Route::get('/mentor',[MentorController::class,'index'])->name('mentor.index');
    Route::post('/mentor',[MentorController::class,'store'])->name('mentor.store');
    Route::get('/dokumen',[DocumentController::class,'index'])->name('documents.index');
    Route::post('/dokumen',[DocumentController::class,'store'])->name('documents.store');
    Route::get('/dokumen/{document}/unduh',[DocumentController::class,'download'])->name('documents.download');
    Route::get('/hadiah',[PrizeController::class,'index'])->name('prizes.index');
    Route::post('/hadiah',[PrizeController::class,'store'])->name('prizes.store');
    Route::post('/hadiah/{prize}/distribusi',[PrizeController::class,'distribute'])->name('prizes.distribute');
    Route::get('/inventaris',[InventoryController::class,'index'])->name('inventory.index');
    Route::post('/inventaris',[InventoryController::class,'store'])->name('inventory.store');
    Route::post('/inventaris/{item}/pergerakan',[InventoryController::class,'move'])->name('inventory.move');
    Route::get('/galeri',[GalleryController::class,'index'])->name('gallery.index');
    Route::post('/galeri',[GalleryController::class,'store'])->name('gallery.store');
    Route::get('/arsip',[PeriodController::class,'archive'])->name('archive.index');
    Route::get('/periode',[PeriodController::class,'index'])->name('periods.index');
    Route::post('/periode',[PeriodController::class,'store'])->name('periods.store');
    Route::patch('/periode/{period}/aktifkan',[PeriodController::class,'activate'])->name('periods.activate');
    Route::get('/panduan',[GuideController::class,'index'])->name('guides.index');
    Route::post('/panduan',[GuideController::class,'store'])->name('guides.store');
});
