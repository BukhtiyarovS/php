<?php

class CreateNewResourceController extends Controller {

	public function createCompany(Request $request) {
		$request->validate([
			'address_1' => 'required|max:100',
			'postcode' => 'required',
			'tax_number' => 'required|unique:App\Models\Company,tax_number',
			'name' => 'required',
			'city' => 'required',
			'email' => 'required|email|unique:App\Models\Company,email',
			'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:7',
			'accountant_program' => 'required',
			'accountant_program_other' => 'required_if:accountant_program,==,other|nullable',
		]);

		$company = $request->all();
		if (isset($company['custom_price']) && $company['custom_price']) {
			session(['custom_price' => $company['custom_price']]);
		}

		$company = Company::query()->create($company);

		return response()->json($company, 200);
	}

	public function createBetaUser(Request $request) {
		$request->validate([
			'email' => 'required|email',
		]);

		return BetaUser::query()->create($request->all());
	}

	public function createSellerGroup(Request $request) {
		$request->validate([
			'name' => 'required',
		]);

		return SellerGroup::query()->create($request->all());
	}

	public function createNews(Request $request) {
		$request->validate([
			'title' => 'required',
			'short' => 'required',
		]);

		return News::query()->create($request->all());
	}

	public function createCompanyTodo(Request $request) {
		$request->validate([
			'date' => 'required',
			'company_id' => 'required',
			'user_id' => 'required',
		]);

		return CompanyTodo::query()->create($request->all());
	}

	public function createLead(Request $request) {
		$request->validate([
			'address_1' => 'required|max:100',
			'tax_number' => 'required|unique:App\Models\Company,tax_number',
			'postcode' => 'required',
			'country' => 'required',
			'city' => 'required',
			'first_name' => 'required',
			'last_name' => 'required',
			'user_type' => 'required',
			'email' => 'required|email|unique:App\Models\Company,email',
			'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:7',
			'date_and_time' => 'required',
			'accountant_program' => 'required',
			'accountant_program_other' => 'required_if:accountant_program,==,other|nullable',
		]);

		$user = $request->all();
		$meetingTime = $user['date_and_time'];
		unset($user['date_and_time']);
		unset($user['name']);
		$user['seller_id'] = Auth::user()->id;
		$user['accepted_terms_at'] = NULL;
		$user['password'] = Hash::make(Str::random(8));
		$user['isSpecialBookkeeper'] = $user['user_type'] === 'special_bookkeeper' ? 1 : 0;
		$user['isExternalBookkeeper'] = $user['user_type'] === 'external_bookkeeper' ? 1 : 0;
		$user = User::create($user);

		$company = $request->all();
		$company['owner_id'] = $user['id'];
		$company['seller_id'] = $user['seller_id'];
		if (isset($company['custom_price']) && $company['custom_price']) {
			session(['custom_price' => $company['custom_price']]);
		}
		Company::$sendEmails = false;
		Company::$attachAuthenticatedUser = false;
		$company = Company::create($company);

		$user->companies()->attach([
			$company->id => [
				'isAdmin' => $user['user_type'] === 'admin',
				'isBookkeeper' => $user['isSpecialBookkeeper'] ? 1 : ($user['isExternalBookkeeper'] ? 1 : 0)
			]
		]);
		$bccEmail = 'admin1@admin.admin';
		Mail::to($user['email'])
			->bcc($bccEmail)
			->send((new SendInvitation($user, $company, $meetingTime))->onQueue('email'));

		$user->forceFill([
			'invitation_email_sent_at' => \Illuminate\Support\Carbon::now(),
		])->save();

		return $company;
	}

	public function createUser(Request $request) {
		$request->validate([
			'first_name' => 'required',
			'last_name' => 'required',
			'email' => 'required|email||unique:App\Models\User,email',
			'password' => 'required',
			'locale' => 'required',
		]);
		$user = $request->all();

		if ($user['password']) {
			$user['password'] = bcrypt($user['password']);
		}

		return User::query()->create($user);
	}

	public function createSeller(Request $request) {
		$request->validate([
			'first_name' => 'required',
			'last_name' => 'required',
			'email' => 'required|email||unique:App\Models\User,email',
			'password' => 'required',
			'locale' => 'required',
			'seller_group_id' => 'required',
		]);
		$user = $request->all();

		if ($user['password']) {
			$user['password'] = bcrypt($user['password']);
		}

		return User::query()->create($user);
	}

	public function createDebtCollection(Request $request) {
		$request->validate([
			'address_1' => 'required|max:100',
			'postcode' => 'required',
			'city' => 'required',
			'invoice' => 'required',
		]);
		$debtCollection = (object)$request->all();

		$debtCollection->dc_number = Str::uuid();
		$company = Auth::company();
		$debtCollection->company_id = $company->id;

		if ($debtCollection->type === 'company') {
			// Check and find if the debtor already exists
			$debtor = Debtor::query()->where([
				'tax_number' => $debtCollection->tax_number,
				'company_id' => $company->id,
			])->first();
		}

		if (!isset($debtor) || $debtCollection->type === 'person') { // Create new debtor
			// Create a new debtor and save it
			$debtor = new Debtor();
			$debtor->fill([
				'name' => $debtCollection->name,
				'email' => $debtCollection->email,
				'tax_number' => $debtCollection->tax_number ?: null,
				'phone' => $debtCollection->phone,
				'address_1' => $debtCollection->address_1,
				'city' => $debtCollection->city,
				'postcode' => $debtCollection->postcode,
				'type' => $debtCollection->type,
				'company_id' => $company->id,
//                        'is_process_started' => $company->automatic_reminder === 1 && $debtCollection->email != null ? 1 : 0
			]);
			$debtor->save();
		}
		// Associate the existing debtor to the debtcollection
		$debtCollection->debtor_id = $debtor->id;

		$debtCollectionNew = new DebtCollection();

		$debtCollectionNew->fill([
			'dc_number' => $debtCollection->dc_number,
			'company_id' => $debtCollection->company_id,
			'debtor_id' => $debtCollection->debtor_id,
			'objection' => $debtCollection->objection ? 1 : 0,
			'objection_description' => $debtCollection->objection_description,
			'objection_files' => $debtCollection->objection_files,
			'status' => 'open',
			'status_updated_at' => null,
		]);

		$debtCollectionNew->save();
		$debtCollection->id = $debtCollectionNew->id;
		$debtCollectionNew->debtor()->associate($debtor);

		// Get the claims data stored in memory
		$claims_arr = array_merge(
			(array)$debtCollection->invoice,
			(array)$debtCollection->debt_reminder,
			(array)$debtCollection->note,
			(array)$debtCollection->payment //installment from create case manually
		);

		$claims = $this->createNewClaims($claims_arr, $debtCollection->id);

		app(Dispatcher::class)->dispatch((new SendCaseToScmJob($debtCollection->id, $claims, $debtCollection->to_email_send))->asManualSend()->useStatusUpdate()->onQueue('high'));
		return $debtCollection->id;
	}

	public function createFilesForDebtCollection(Request $request, $type) {
		$files = [];
		foreach ($request->all() as $file) {
			$company = Auth::company();
			$pathName = Claim::getStoragePath($type, $company->id);
			$pathName = substr($pathName, 0, -1);
			$files[] = Storage::disk('s3')->put($pathName, $file);
		}
		return $files;
	}

	public function createNewClaims($claims_arr, $id): array {
		$claims = [];

		foreach ($claims_arr as $c) {

			$claims[] = Claim::create(array_merge(
				$c['attributes'],
				[
					'debt_collection_id' => $id,
					'date' => isset($c['attributes']['date']) ? Carbon::parse($c['attributes']['date']) : Carbon::today(),
					'due_date' => isset($c['attributes']['due_date']) ? Carbon::parse($c['attributes']['due_date']) : Carbon::today(),
					'amount' => isset($c['attributes']['amount']) ? (float)$c['attributes']['amount'] : null,
					'amount_due' => isset($c['attributes']['amount_due']) ? (float)$c['attributes']['amount_due'] : null,
					'file' => $c['attributes']['file'] ?? '',
					'currency' => 'DKK',
				]
			));

		}
		return $claims;
	}

}
