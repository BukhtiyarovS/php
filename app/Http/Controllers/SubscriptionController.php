<?php

class SubscriptionController extends Controller {

	public function currentSubscription($getCompany = null) {
		isset($getCompany) && $getCompany->id ? $company = $getCompany : $company = Auth::company();

		$subscriptionsLast = Subscription::query()->where('company_id', '=', $company->id)
			->orderBy('subscribed_at', 'desc')
			->with('plan')
			->first();

		$subscription = Subscription::query()->where('company_id', '=', $company->id)
			->orderBy('subscribed_at')
			->where('plan_id', '=', $company->plan_id)
			->where('expires_at', '>', Carbon::now())
			->with('plan')
			->first();

		empty($subscription) ? $subscription = $subscriptionsLast : $subscription->expires_at = $subscriptionsLast->expires_at;
		return $subscription;
	}

	public function firstSubscription($getCompany = null) {
		isset($getCompany) && $getCompany->id ? $company = $getCompany : $company = Auth::company();

		return Subscription::query()->where('company_id', '=', $company->id)
			->orderBy('subscribed_at')
			->where('plan_id', '=', $company->plan_id)
			->where('expires_at', '>', Carbon::now())
			->first();
	}

	public function updateSubscriptionFromSettings($id) {
		$company = Auth::company();
		$subscription = Subscription::where('company_id', '=', $company->id)->orderBy('expires_at', 'desc')->first();
		$plan = Plan::find($id);

		$subscription->update([
			'plan_id' => $id,
			'renewal_frequency' => $plan->renewal_interval_months == 12 ? 'yearly' : "each {$plan->renewal_interval_months} months",
			'price' => $plan->price,
			'percent' => $plan->percent,
			'subscribed_at' => Carbon::now(),
			'expires_at' => Carbon::now()->addMonths($plan->renewal_interval_months)->toDateTimeString(),
		]);
		$company->update(['plan_id' => $id]);

		$subscription->load('plan');
		return response()->json($subscription);
	}


}
