<?php

namespace App\Http\Controllers\Back\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class QuickMenuController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function cache()
    {
        Artisan::call('optimize:clear');
        
//        Artisan::call('cache:clear');
//        Artisan::call('config:clear');
//        Artisan::call('view:clear');
//        Artisan::call('route:clear');
    
        return redirect()->back()->with('success', 'Cache Cleared succesfully!');
    }
    
    
    /**
     * Maintenance Mode ON.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function maintenanceModeON()
    {
        Artisan::call('down');
        
        return redirect()->back()->with('success', 'Application is now in maintenance mode.');
    }
    
    
    /**
     * Maintenance Mode OFF.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function maintenanceModeOFF()
    {
        Artisan::call('up');
        
        return redirect()->back()->with('success', 'Application is now live.');
    }
    
}
