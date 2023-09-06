<?php

class CompanyListController extends Controller {

	public function openOverduePage(Company $company) {
		Auth::setCompany($company);
		return redirect('/overdue-invoices');
	}

	public function setCompany(Request $request, Company $company) {
		Auth::setCompany($company);
		return redirect('/overdue-invoices')->with('reminder_system_data', $request->get('reminder_system_data'));
	}

	public function headerSetCompany(Company $company) {
		Auth::setCompany($company);
		return back();
	}

	public function importState() {
		return [
			'importing' => Auth::company()->importRunning(),
		];
	}

	public function getCases() {
		try {
			return resolve(DebtCollectionService::class)->getCompanyDebtCollections(Auth::company());
		} catch (\Exception $e) {
			Log::error('Error message:' . $e->getMessage());
			return $e->getMessage();
		}
	}
}
