<?php

class PlanController extends Controller {
	public function plansList() {
		return Plan::all();
	}

	public function filteredPlans($lowAccess = 0) {
		if ((Auth::user()->siteAdmin() || Auth::user()->is_seller) & !$lowAccess) {
			return Plan::query()->whereNotIn('id', [2, 6, 7, 8, 9])->get();
		}
		return Plan::query()->whereIn('id', [1, 10])->get();
	}

	public function leadPlanInfo($leadId) {
		$closedLead = ClosedLead::find($leadId);

		$result = array();
		$result['companies'] = array();

		if (!$closedLead || !count($closedLead->companies)) {
			abort(404);
		}

		foreach ($closedLead->companies as $company) {
			$resultCompany = array();

			$resultCompany['companyId'] = $company->id;
			$resultCompany['name'] = $company->name;
			$resultCompany['subscription'] = $company->getCurrentSubscription();

			$result['companies'][] = $resultCompany;
		}
		return $result;
	}

	public function updatePlan(Request $request) {

		$companyId = $request->input('companyId');
		$newPlanId = $request->input('newPlanId');
		$newPrice = $request->input('newPrice');
		$expireDate = $request->input('expireDate');
		$isChanged = $request->input('isChanged');


		$company = Company::find($companyId);
		$selectedPlan = Plan::find($newPlanId);
		$user = User::find($company->owner_id);

		if (!$company || !$selectedPlan) {
			abort(404);
		}

		if ($isChanged) {
			Subscription::query()->where('company_id', '=', $company->id)->delete();
		}
		$newSubscription = new Subscription();

		$newSubscription->company_id = $company->id;
		$newSubscription->plan_id = $selectedPlan->id;
		$newSubscription->renewal_frequency = $selectedPlan->renewal_interval_months == 12 ? 'yearly' : "each {$selectedPlan->renewal_interval_months} months";
		$newSubscription->price = $newPrice;
		$newSubscription->percent = $selectedPlan->percent;
		$newSubscription->subscribed_at = Carbon::now();
		$newSubscription->expires_at = $expireDate;
		$newSubscription->save();

		if ($company->plan_id === 1 || $selectedPlan->id === 1) {
			// plan has changed to/from One
			$company->plan_id = $selectedPlan->id;
			$company->save();
		} else {
			// plan has changed from Plus to Plus
			// Send mail to Admin with info about the new plan selected
			$plan = Plan::find($company->plan_id);
			Mail::to(Config::get('mail.send_to_admin'))->queue(
				(new SendCompanyPlanAdminMail($user, $company, $plan, 'change'))->onQueue('email')
			);
		}

		// sending emails
		if ($company->plan_id === 2 || $company->plan_id === 6 || $company->plan_id === 7) {
			$currentSubscription = Subscription::query()->where('company_id', '=', $company->id)
				->orderBy('subscribed_at', 'asc')
				->where('plan_id', '=', $company->plan_id)
				->where('expires_at', '>', Carbon::now())
				->with('plan')
				->first();
		} else {
			$currentSubscription = $company->getCurrentSubscription();
		}

		$subscription = Plan::query()->where('id', '=', $newPlanId)->first();
		$user = User::find($company->owner_id);
		Mail::to($user->email)->queue((new SendPlanUpgradeMail($user, $company, $currentSubscription, $subscription))->onQueue('email'));
	}
}
