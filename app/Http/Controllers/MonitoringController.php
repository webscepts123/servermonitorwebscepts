<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function uptime()
    {
        return view('monitoring.uptime');
    }

    public function ports()
    {
        return view('monitoring.ports');
    }

    public function services()
    {
        return view('monitoring.services');
    }

    public function resources()
    {
        return view('monitoring.resources');
    }
}