<?php

class UpdateCompaniesPlan implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public int $companyId;

	/**
	 * Create a new job instance.
	 *
	 * @param int $companyId
	 */
	public function __construct(int $companyId = 0) {
		$this->companyId = $companyId;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle() {
		try {
			log::info('Update companies plan start');
			if ($this->companyId === 0) {
				$companies = Company::all();
				foreach ($companies as $company) {
					$this->updateCompanyPlan($company);
				}
				Log::info('All companies plan has been updated!');
			} else {
				$this->updateCompanyPlan(Company::find($this->companyId));
			}
		} catch (\Throwable $exception) {
			Log::error('Update companies plan job Error: ' . $exception->getMessage());
			Log::info('---end UpdateCompaniesPlan job error----');
		}
	}

	private function updateCompanyPlan($company) {
		if (empty($company->subscription)) {
			$company->plan_id = 1;
			$this->updateCompany($company);
			return;
		}
		if ($company->plan_id === 1 && $company->subscription->plan_id === 1) return;
		$subscription = Subscription::query()->where('company_id', '=', $company->id)
			->orderBy('subscribed_at', 'asc')
			->where('plan_id', '!=', 1)
			->where('expires_at', '>', Carbon::today())
			->first();
		empty($subscription) ? $company->plan_id = 1 : $company->plan_id = $subscription->plan_id;

		$this->updateCompany($company);
	}

	private function updateCompany($company) {
		if ($company->plan_id === 1) {
			$newSubscription = new Subscription();
			$newSubscription->company_id = $company->id;
			$newSubscription->plan_id = $company->plan_id;
			$newSubscription->renewal_frequency = 'yearly';
			$newSubscription->price = 0;
			$newSubscription->percent = 20;
			$newSubscription->subscribed_at = Carbon::now();
			$newSubscription->expires_at = Carbon::today()->addYear();
			$newSubscription->save();
			$company->reminder_flow = 1;
		}
		$company->isSendMail = false;
		$company->save();
	}
}
