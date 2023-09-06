<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ArtisanController extends Controller {
	//endpoint artisan commands only for siteAdmin

	public function clearOptimize() {
		if (Auth::user()->siteAdmin()) {
			Artisan::call('optimize:clear');
			return 'Optimize has been cleared';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function clearCache() {
		if (Auth::user()->siteAdmin()) {
			Artisan::call('cache:clear');
			return 'Cache has been cleared';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function clearConfig() {
		if (Auth::user()->siteAdmin()) {
			Artisan::call('config:clear');
			return 'Config has been cleared';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function clearView() {
		if (Auth::user()->siteAdmin()) {
			Artisan::call('view:clear');
			return 'View has been cleared';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function clearRoute() {
		if (Auth::user()->siteAdmin()) {
			Artisan::call('route:clear');
			return 'Route has been cleared';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function cacheRoute() {
		if (Auth::user()->siteAdmin()) {
			Artisan::call('route:cache');
			return 'Route cache has been cache';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function cacheConfig() {
		if (Auth::user()->siteAdmin()) {
			Artisan::call('config:cache');
			return 'Config cache has been cache';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function cacheView() {
		if (Auth::user()->siteAdmin()) {
			Artisan::call('view:cache');
			return 'View cache has been cache';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function migration() {
		if (Auth::user()->siteAdmin()) {
			try {
				Artisan::call('migrate');
			} catch (\Throwable $e) {
				Log::error('Error message:' . $e->getMessage());
				echo $e->getMessage();
				return $e->getMessage();
			}
			return 'migration successful';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function rollback() {
		if (Auth::user()->siteAdmin()) {
			try {
				Artisan::call('migrate:rollback');
			} catch (\Throwable $e) {
				Log::error('Error message:' . $e->getMessage());
				echo $e->getMessage();
				return $e->getMessage();
			}
			return 'rollback successful';
		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function test1() {
		if (Auth::user()->siteAdmin()) {

		} else {
			abort(403, 'Unauthorized action.');
		}
	}

	public function test2() {
		if (Auth::user()->siteAdmin()) {

		} else {
			abort(403, 'Unauthorized action.');
		}
	}
}
