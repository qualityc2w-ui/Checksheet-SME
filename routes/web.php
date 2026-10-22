<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\MasterDataController;
use Illuminate\Support\Facades\Route;

Route::get('/qc/evidence/{inspection}/{section}/{item}', [InspectionController::class, 'sharedEvidence'])
    ->middleware('signed')
    ->name('inspections.shared-evidence');

Route::middleware('guest')->group(function () {
    Route::get('/login', [\App\Http\Controllers\AuthController::class, 'create'])->name('login');
    Route::post('/login', [\App\Http\Controllers\AuthController::class, 'store'])->name('login.store');
});

    Route::get('/findings/respond/{token}', [FindingController::class, 'respond'])->name('findings.respond');
    Route::post('/findings/respond/{token}/plan', [FindingController::class, 'submitPlan'])->name('findings.respond.plan');
    Route::post('/findings/respond/{token}/completion', [FindingController::class, 'submitCompletion'])->name('findings.respond.completion');
    Route::get('/findings/respond/{token}/photos/{kind}', [FindingController::class, 'responsePhoto'])
        ->where('kind', 'problem|completion')
        ->name('findings.respond.photo');

    Route::middleware('auth')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/inspections/{inspection}/findings', [FindingController::class, 'resumeInspection'])->name('findings.inspection.next');
    Route::get('/inspections/{inspection}/findings/{section}/{item}', [FindingController::class, 'createFromInspection'])
        ->where('section', 'dimensions|visual|function')
        ->whereNumber('item')
        ->name('findings.inspection.create');
    Route::post('/inspections/{inspection}/findings/{section}/{item}', [FindingController::class, 'storeFromInspection'])
        ->where('section', 'dimensions|visual|function')
        ->whereNumber('item')
        ->name('findings.inspection.store');
    Route::get('/inspections/{inspection}/findings/{section}/{item}/photo', [FindingController::class, 'inspectionPhoto'])
        ->where('section', 'dimensions|visual|function')
        ->whereNumber('item')
        ->name('findings.inspection.photo');

    Route::get('/findings/dashboard', [FindingController::class, 'dashboard'])->name('findings.dashboard');
    Route::get('/findings', [FindingController::class, 'index'])->name('findings.index');
    Route::get('/findings.csv', [FindingController::class, 'export'])->name('findings.export');
    Route::get('/findings/create', [FindingController::class, 'create'])->name('findings.create');
    Route::post('/findings', [FindingController::class, 'store'])->name('findings.store');
    Route::get('/findings/settings', [FindingController::class, 'settings'])->name('findings.settings');
    Route::post('/findings/settings/pics', [FindingController::class, 'storePic'])->name('findings.pics.store');
    Route::put('/findings/settings/pics/{pic}', [FindingController::class, 'updatePic'])->name('findings.pics.update');
    Route::delete('/findings/settings/pics/{pic}', [FindingController::class, 'destroyPic'])->name('findings.pics.destroy');
    Route::post('/findings/settings/options', [FindingController::class, 'storeOption'])->name('findings.options.store');
    Route::delete('/findings/settings/options/{option}', [FindingController::class, 'destroyOption'])->name('findings.options.destroy');
    Route::get('/findings/{finding}/photos/{kind}', [FindingController::class, 'photo'])
        ->where('kind', 'problem|completion')
        ->name('findings.photo');
    Route::post('/findings/{finding}/email', [FindingController::class, 'sendEmail'])->name('findings.email');
    Route::post('/findings/{finding}/verify', [FindingController::class, 'verify'])->name('findings.verify');
    Route::get('/findings/{finding}/edit', [FindingController::class, 'edit'])->name('findings.edit');
    Route::put('/findings/{finding}', [FindingController::class, 'update'])->name('findings.update');
    Route::delete('/findings/{finding}', [FindingController::class, 'destroy'])->name('findings.destroy');
    Route::get('/findings/{finding}', [FindingController::class, 'show'])->name('findings.show');

    Route::get('/inspections', [InspectionController::class, 'index'])->name('inspections.index');
    Route::post('/inspections', [InspectionController::class, 'store'])->name('inspections.store');
    Route::post('/inspections/{inspection}/email', [InspectionController::class, 'sendEmail'])
        ->name('inspections.email');
    Route::post('/inspections/{inspection}/approve', [InspectionController::class, 'approve'])
        ->name('inspections.approve');
    Route::get('/point-check-images/{visualStandard}', [InspectionController::class, 'pointCheckImage'])
        ->name('inspections.point-check-image');
    Route::get('/product-point-check-images/{product}', [InspectionController::class, 'productPointCheckImage'])
        ->name('products.point-check-image');
    Route::get('/inspections/{inspection}/evidence/{index}', [InspectionController::class, 'evidence'])
        ->whereNumber('index')
        ->name('inspections.evidence');
    Route::delete('/inspections/{inspection}', [InspectionController::class, 'destroy'])->name('inspections.destroy');
    Route::get('/inspection-reports', [InspectionController::class, 'history'])->name('inspection-reports.index');
    Route::get('/inspection-reports.xlsx', [InspectionController::class, 'exportExcel'])->name('inspection-reports.export');
    Route::get('/inspection-reports.pdf', [InspectionController::class, 'reportPdf'])->name('inspection-reports.pdf');
    Route::get('/inspections/report', [InspectionController::class, 'report'])->name('inspections.report');
    Route::get('/inspections/report.pdf', [InspectionController::class, 'reportPdf'])->name('inspections.report.pdf');
    Route::get('/inspections/{inspection}/report', [InspectionController::class, 'reportInspection'])->name('inspections.report.single');
    Route::get('/inspections/{inspection}/report.pdf', [InspectionController::class, 'reportInspectionPdf'])->name('inspections.report.single.pdf');

    Route::get('/master/{section?}', [MasterDataController::class, 'index'])
        ->where('section', 'products|inspectors|defects|areas|accounts|dimension-standards|visual-standards|function-standards')
        ->name('master.index');
    Route::post('/master/{section}', [MasterDataController::class, 'store'])
        ->where('section', 'products|inspectors|defects|areas|accounts|dimension-standards|visual-standards|function-standards')
        ->name('master.store');
    Route::put('/master/{section}/{record}', [MasterDataController::class, 'update'])
        ->where('section', 'products|inspectors|defects|areas|accounts|dimension-standards|visual-standards|function-standards')
        ->name('master.update');
    Route::delete('/master/{section}/{record}', [MasterDataController::class, 'destroy'])
        ->where('section', 'products|inspectors|defects|areas|accounts|dimension-standards|visual-standards|function-standards')
        ->name('master.destroy');
});
