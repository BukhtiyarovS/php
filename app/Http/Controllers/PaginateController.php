<?php

class PaginateController extends Controller {

	public function paginateBetaUsers(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return BetaUser::query()
			->where('email', 'like', '%' . $paginate['search'] . '%')
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateBookkeepers(Request $request) {
		$paginate = $request->all();
		$company = Auth::company();
		$user_ids = $company->bookkeeperName()->pluck('id');

		return Bookkeeper::query()
			->where(function ($query) {
				$query->whereHas('bookkeepers', function ($query) {
					$query->where('isBookkeeper', 1);
				});
			})
			->where(function ($query) use ($paginate) {
				$query->where('id', '=', $paginate['search'])
					->orwhere('id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('first_name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('last_name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('email', 'like', '%' . $paginate['search'] . '%');
			})
			->where(function ($query) use ($user_ids) {
				if (!Auth::user()->siteAdmin()) {
					$query->whereIn('id', $user_ids);
				}
			})
			->with('companies')
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateClaims(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return Claim::query()
			->with('debtcollection')
			->where('date', '>', 0)
			->where('due_date', '>', 0)
			->where(function ($query) use ($paginate) {
				$query->where('debt_collection_id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('claim_number', 'like', '%' . $paginate['search'] . '%')
					->orWhere('date', 'like', '%' . $paginate['search'] . '%')
					->orWhere('due_date', 'like', '%' . $paginate['search'] . '%');
			})
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateCompanies(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			if (!Auth::user()->is_seller) {
				abort(403, 'Unauthorized action.');
			}
		}
		$paginate = $request->all();
		$today = \Carbon\Carbon::today();
		return Company::query()
			->join('users', 'companies.owner_id', '=', 'users.id')
			->join('plans', 'companies.plan_id', '=', 'plans.id')
			->select('companies.*',
				'users.first_name', 'users.last_name', 'users.isExternalBookkeeper', 'users.isSpecialBookkeeper',
				'plans.name as plan_name',
				DB::raw("(select price from subscriptions where company_id = companies.id and expires_at > '$today' order by expires_at asc limit 1) as subscription_price"),
				DB::raw("(select percent from subscriptions where company_id = companies.id and expires_at > '$today' order by expires_at asc limit 1) as subscription_percent"),
				DB::raw("(select id from subscriptions where company_id = companies.id and expires_at > '$today' order by expires_at asc limit 1) as subscription_id"),
				DB::raw("(select expires_at from subscriptions where company_id = companies.id and expires_at > '$today' order by expires_at asc limit 1) as subscription_expires_at"),
				DB::raw("(select expires_at from subscriptions where company_id = companies.id and expires_at > '$today' order by expires_at asc limit 1) as subscription_expires_at"),
				DB::raw("(select text from company_todos where company_id = companies.id order by date asc limit 1) as company_todo_text"),
				DB::raw("(select date from company_todos where company_id = companies.id order by date asc limit 1) as company_todo_date"),
				DB::raw("(select user_id from company_todos where company_id = companies.id order by date asc limit 1) as company_todo_user"),
				DB::raw("(select id from company_todos where company_id = companies.id order by date asc limit 1) as company_todo_id"),
				DB::raw("(select collection_sent_date from debtors where company_id = companies.id and collection_sent_date IS NOT NULL order by collection_sent_date desc limit 1) as debtor_last_send_case"),
				DB::raw("(select COUNT(DISTINCT debt_collections.debtor_id) from debt_collections
                join claims on claims.debt_collection_id = debt_collections.id
                join debtors on debtors.id = debt_collections.debtor_id
                where debt_collections.company_id = companies.id and status = 'open' and claims.type = 'invoice'
                and debtors.is_ignored = '0' and debtors.is_collection_sent = '0' and debt_collections.deleted_at IS NULL
                and claims.due_date < '$today' and claims.amount_due > '5') as debt_collections_debtors"),
			)
			->withCount('sendCases')
			->with('integration')
			->with('isProcessStarted', function ($query) {
				$query->where('is_process_started', '=', 1);
			})
			->where(function ($query) use ($paginate) {
				$query->where('companies.name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('companies.id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('companies.tax_number', 'like', '%' . $paginate['search'] . '%')
					->orwhere('users.first_name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('users.last_name', 'like', '%' . $paginate['search'] . '%');
			})
			->where(function ($query) use ($paginate) {
				if ($paginate['integrationStatus'] == '') return $query;
				if ($paginate['integrationStatus'] == 'error') return $query->where('integration_status', 'like', '%' . $paginate['integrationStatus'] . '%');
				return $query->where('integration_status', 'like', $paginate['integrationStatus']);
			})
			->where(function ($query) use ($paginate) {
				if ($paginate['accountantProgram'] == '') return $query;
				return $query->where('accountant_program', 'like', $paginate['accountantProgram']);
			})
			->whereNotNull('users.accepted_terms_at')
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateTodos(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			if (!Auth::user()->is_seller) {
				abort(403, 'Unauthorized action.');
			}
		}
		$paginate = $request->all();
		return CompanyTodo::query()
			->join('users', 'company_todos.user_id', '=', 'users.id')
			->join('companies', 'company_todos.company_id', '=', 'companies.id')
			->select('company_todos.id', 'company_todos.user_id', 'company_todos.company_id', 'company_todos.date',
				'company_todos.text', 'companies.name as company_name', 'users.first_name', 'users.last_name')
			->where(function ($query) use ($paginate) {
				$query->where('company_todos.id', '=', $paginate['search'])
					->orwhere('company_todos.id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('company_todos.date', 'like', '%' . $paginate['search'] . '%')
					->orWhere('company_todos.text', 'like', '%' . $paginate['search'] . '%')
					->orWhere('companies.name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('users.first_name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('users.last_name', 'like', '%' . $paginate['search'] . '%');
			})
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateDebtCollections(Request $request) {
		$paginate = $request->all();
		$company = Auth::company();
		return DebtCollection::query()
			->where('status', 'like', 'open')
			->where(function ($query) use ($company) {
				if (Auth::user()->siteAdmin() || Auth::user()->is_seller) {
					return $query;
				} else {
					$query->where('company_id', $company->id);
				}
			})
			->with('company')
			->with('debtor')
			->with('claims')
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateDebtors(Request $request) {
		$paginate = $request->all();
		$company = Auth::company();
		return Debtor::query()
			->join('companies', 'debtors.company_id', '=', 'companies.id')
			->select('debtors.id', 'debtors.company_id', 'debtors.name', 'debtors.tax_number', 'debtors.type', 'companies.name as company_name')
			->where(function ($query) use ($paginate) {
				$query->where('debtors.name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('debtors.tax_number', 'like', '%' . $paginate['search'] . '%')
					->orWhere('companies.name', 'like', '%' . $paginate['search'] . '%');
			})
			->where(function ($query) use ($company) {
				if (!Auth::user()->siteAdmin()) {
					$query->where('company_id', $company->id);
				}
			})
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginatePartners(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return IntegrationPartner::query()
			->where(function ($query) use ($paginate) {
				$query->where('id', '=', $paginate['search'])
					->orwhere('id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('name', 'like', '%' . $paginate['search'] . '%');
			})
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateLeads(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			if (!Auth::user()->is_seller) {
				abort(403, 'Unauthorized action.');
			}
		}
		$paginate = $request->all();
		return Lead::query()
			->where(function ($query) use ($paginate) {
				$query->where('id', '=', $paginate['search'])
					->orwhere('id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('first_name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('last_name', 'like', '%' . $paginate['search'] . '%');
			})
			->whereNotNull('invitation_email_sent_at')
			//->join('companies', 'companies.owner_id', '=', 'users.id')
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateNews(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return News::query()
			->where(function ($query) use ($paginate) {
				$query->where('id', '=', $paginate['search'])
					->orwhere('id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('title', 'like', '%' . $paginate['search'] . '%')
					->orWhere('short', 'like', '%' . $paginate['search'] . '%');
			})
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginatePlans(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return Plan::query()
			->where(function ($query) use ($paginate) {
				$query->where('id', '=', $paginate['search'])
					->orwhere('id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('price', 'like', '%' . $paginate['search'] . '%');
			})
			->withCount('companies')
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateSellers(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return Seller::query()
			->join('seller_groups', 'users.seller_group_id', '=', 'seller_groups.id')
			->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.seller_group_id', 'seller_groups.name as seller_groups_name')
			->where(function ($query) use ($paginate) {
				$query->where('users.id', '=', $paginate['search'])
					->orwhere('users.id', 'like', '%' . $paginate['search'] . '%')
					->orwhere('users.first_name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('users.last_name', 'like', '%' . $paginate['search'] . '%');
			})
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateSellerGroup(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return SellerGroup::query()
			->where(function ($query) use ($paginate) {
				$query->where('id', '=', $paginate['search'])
					->orwhere('id', 'like', '%' . $paginate['search'] . '%')
					->orwhere('name', 'like', '%' . $paginate['search'] . '%');
			})
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateSubscriptions(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return Subscription::query()
			->join('companies', 'subscriptions.company_id', '=', 'companies.id')
			->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
			->select('companies.id as company_id', 'companies.name as company_name', 'plans.id as plan_id', 'plans.name as plan_name', 'plans.price as plan_price',
				'subscriptions.id', 'subscriptions.subscribed_at', 'subscriptions.expires_at', 'subscriptions.price')
			->where(function ($query) use ($paginate) {
				$query->where('companies.name', 'like', '%' . $paginate['search'] . '%')
					->orwhere('plans.name', 'like', '%' . $paginate['search'] . '%')
					->orwhere('subscriptions.price', 'like', '%' . $paginate['search'] . '%')
					->orWhere('subscriptions.subscribed_at', 'like', '%' . $paginate['search'] . '%')
					->orWhere('subscriptions.expires_at', 'like', '%' . $paginate['search'] . '%');
			})
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateUsers(Request $request) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		$paginate = $request->all();
		return User::query()
			->where(function ($query) use ($paginate) {
				$query->where('id', '=', $paginate['search'])
					->orwhere('id', 'like', '%' . $paginate['search'] . '%')
					->orWhere('first_name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('last_name', 'like', '%' . $paginate['search'] . '%')
					->orWhere('email', 'like', '%' . $paginate['search'] . '%');
			})
			->with('companies')
			->orderBy($paginate['column'], $paginate['direction'])
			->paginate($paginate['count']);
	}

	public function paginateCases(Request $request) {
		$paginate = $request->all();
		$pageCount = $paginate['count'] * $paginate['page'];
		try {
			$cases = resolve(DebtCollectionService::class)->getCompanyAllDebtCollections(Auth::company());
			$casesChunk = $cases->chunk($paginate['count']);
			$paginateCases['data'] = $casesChunk[$paginate['page'] - 1];
			$paginateCases['total'] = count($cases);
			$paginateCases['from'] = 1 + $paginate['count'] * ($paginate['page'] - 1);
			$paginateCases['to'] = $pageCount > count($cases) ? count($cases) : $pageCount;
			$paginateCases['last_page'] = count($casesChunk) === 1 ? 1 : count($casesChunk) - 1;
			$paginateCases['current_page'] = (int)$paginate['page'];
			return $paginateCases;
		} catch (\Throwable $e) {
			Log::error('Error message:' . $e->getMessage());
			return $e->getMessage();
		}
	}

}
