<?php

class SaveTodayData implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	//save some data for Statistics page
	//in development, for now only save to database
	//wanted to do it on the statistics page, but then there were no necessary tools

	public function __construct() {
	}


	public function handle() {
		$countClaims = Claim::all()->count();
		$countCompanies = Company::all()->count();
		$countCompanyUsers = CompanyUser::all()->count();
		$countDebtors = Debtor::all()->count();
		$countDebtCollections = DebtCollection::all()->count();
		$countIntegrations = Integration::all()->count();
		$countReminders = Reminder::all()->count();
		$countReminderPosts = ReminderPost::all()->count();
		$countSubscriptions = Subscription::all()->count();
		$countUsers = User::all()->count();
		$counts = [
			'claims' => $countClaims,
			'companies' => $countCompanies,
			'companyUsers' => $countCompanyUsers,
			'debtors' => $countDebtors,
			'debt_collections' => $countDebtCollections,
			'integrations' => $countIntegrations,
			'reminders' => $countReminders,
			'reminder_posts' => $countReminderPosts,
			'subscriptions' => $countSubscriptions,
			'users' => $countUsers,
		];

		$plansWithCompaniesCount = Plan::query()->withCount('companies')->get();
		$plans = [];
		foreach ($plansWithCompaniesCount as $plan) {
			$plans[$plan->id] = $plan->companies_count;
		}

		$integrationStatus['unconnected'] = Company::query()->where('integration_status', 'unconnected')->count();
		$integrationStatus['connected'] = Company::query()->where('integration_status', 'connected')->count();
		$integrationStatus['error'] = Company::query()->whereIn('integration_status', ['error', 'error 1', 'error 2', 'error 3', 'error 4', 'error 5',])->count();

		$integrationPartnersAll = IntegrationPartner::all();
		$integrationPartners = [];
		foreach ($integrationPartnersAll as $partner) {
			$integrationPartners[$partner->id] = Integration::query()->where('integration_partner_id', $partner->id)->count();
		}

		Statistics::create([
			'counts' => $counts,
			'plans' => $plans,
			'integration_status' => $integrationStatus,
			'integration_partners' => $integrationPartners,
		]);

	}
}
