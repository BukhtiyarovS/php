<?php

class EditResourceController extends Controller {

	public function editBookkeeper(Request $request) {
		$request->validate([
			'first_name' => 'required',
			'last_name' => 'required',
		]);

		$bookkeeper = Bookkeeper::find($request->id);
		$bookkeeperUpdate = $request->all();

		if ($bookkeeperUpdate['password']) {
			$bookkeeperUpdate['password'] = bcrypt($bookkeeperUpdate['password']);
		}

		$bookkeeperUpdate = $this->unsetEmpty($bookkeeperUpdate);

		$bookkeeper->update($bookkeeperUpdate);
	}

	public function editDebtor(Request $request) {
		$request->validate([
			'email' => 'required',
			'phone' => 'required',
			'address_1' => 'required',
			'city' => 'required',
			'postcode' => 'required',
		]);

		$debtor = Debtor::find($request->id);
		$debtorUpdate = $request->all();

		$debtorUpdate = $this->unsetEmpty($debtorUpdate);

		$debtor->update($debtorUpdate);
	}

	public function editCompany(Request $request) {
		$request->validate([
			'address_1' => 'required|max:100',
			'postcode' => 'required',
			'city' => 'required',
			'email' => 'required|email',
			'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:7',
			'accountant_program' => 'required',
			'accountant_program_other' => 'required_if:accountant_program,==,other|nullable',
		]);

		$company = Company::find($request['id']);
		$companyUpdate = $request->all();

		$companyUpdate = $this->unsetEmpty($companyUpdate);

		$selectedPlan = request('plan_id') ? Plan::find(request('plan_id')) : Plan::find(1);
		$subscription = Subscription::query()->where('company_id', $request['id'])->orderBy('expires_at', 'desc')->first();

		if (isset($subscription) && $selectedPlan->id !== $subscription->plan->id) {
			$subscription->plan_id = $selectedPlan->id;
			$subscription->renewal_frequency = $selectedPlan->renewal_interval_months === 12 ? 'yearly' : "each {$selectedPlan->renewal_interval_months} months";
			$subscription->price = $companyUpdate['custom_price'] ?? $selectedPlan->price;
			$subscription->percent = $selectedPlan->percent;
			$subscription->subscribed_at = Carbon::now();
			$subscription->expires_at = Carbon::now()->addMonths($selectedPlan->renewal_interval_months)->toDateTimeString();
			$subscription->save();
		}

		unset($companyUpdate['custom_price']);
		$company->update($companyUpdate);

		return response()->json($company, 200);
	}

	public function editCompanyLogo(Request $request) {
		$request->validate([
			'image' => 'image|mimes:jpg,jpeg,png|max:2048',
		]);

		$company = Company::find($request['id']);
		$fileInstance = $request->file('image');

		$fileNameStore = time() . $fileInstance->getClientOriginalName();
		$filePath = 'images/' . $fileNameStore;

		$path = Storage::disk('s3')->put($filePath, $fileInstance);

		$companyUpdate['image'] = Storage::disk('s3')->url($path);

		$company->update($companyUpdate);

		return response()->json($company, 200);
	}

	public function editDebtCollection(Request $request) {
		$request->validate([
			'invoice' => 'required',
		]);

		$editDebtcollection = (object)$request->all();
		$debtCollection = DebtCollection::find($editDebtcollection->id);
		$claimsId = [];
		Claim::query()->where('debt_collection_id', $debtCollection->id)->each(function ($claim) use (&$claimsId) {
			$claimsId[$claim->id] = $claim->id;
		});
		//$company = Company::find($editDebtcollection->company_id);
		//$debtor = Debtor::find($editDebtcollection->debtor_id);

		$debtCollection->objection = $editDebtcollection->objection ? 1 : 0;
		$debtCollection->objection_description = $editDebtcollection->objection_description;
		$debtCollection->objection_files = $editDebtcollection->objection_files;
		$debtCollection->status = $editDebtcollection->status;
		$debtCollection->update();

		// Get the claims data stored in memory
		$claims_arr = array_merge(
			(array)$editDebtcollection->invoice,
			(array)$editDebtcollection->debt_reminder,
			(array)$editDebtcollection->note,
			(array)$editDebtcollection->payment //installment
		);

		// Create new claims or update existing
		foreach ($claims_arr as $c) {

			if (isset($c['attributes']['id'])) {
				$claim = Claim::find($c['attributes']['id']);
				$claim->update($c['attributes']);
				$isExists = array_search($c['attributes']['id'], $claimsId);
				if ($isExists) {
					unset($claimsId[$isExists]);
				}
				continue;
			}

			Claim::create(array_merge(
				$c['attributes'],
				[
					'debt_collection_id' => $debtCollection->id,
					'date' => isset($c['attributes']['date']) ? Carbon::parse($c['attributes']['date']) : null,
					'due_date' => isset($c['attributes']['due_date']) ? Carbon::parse($c['attributes']['due_date']) : null,
					'amount' => isset($c['attributes']['amount']) ? (float)$c['attributes']['amount'] : null,
					'amount_due' => isset($c['attributes']['amount_due']) ? (float)$c['attributes']['amount_due'] : null,
					'file' => $c['attributes']['file'] ?? '',
					'currency' => 'DKK',
				]
			));
		}
		foreach ($claimsId as $claimId) {
			Claim::find($claimId)->delete();
		}
		//app(Dispatcher::class)->dispatch((new SendCaseToScmJob($debtCollection->id, $claims))->asManualSend()->useStatusUpdate()->onQueue('high'));
	}

	public function editPartner(Request $request) {
		$request->validate([
			'name' => 'required',
			'logo' => 'required',
		]);

		$partner = IntegrationPartner::find($request->id);
		$partnerUpdate = $request->all();

		$partnerUpdate = $this->unsetEmpty($partnerUpdate);

		$partner->update($partnerUpdate);
	}

	public function editNews(Request $request) {
		$request->validate([
			'title' => 'required',
			'short' => 'required',
		]);

		$news = News::find($request->id);
		$newsUpdate = $request->all();

		$newsUpdate = $this->unsetEmpty($newsUpdate);

		$news->update($newsUpdate);
	}

	public function editCompanyTodo(Request $request) {
		$request->validate([
			'date' => 'required',
			'text' => 'required',
		]);

		$companyTodo = CompanyTodo::find($request->id);
		$companyTodoUpdate = $request->all();

		$companyTodoUpdate = $this->unsetEmpty($companyTodoUpdate);

		$companyTodo->update($companyTodoUpdate);
	}

	public function editPlan(Request $request) {
		$request->validate([
			'name' => 'required',
			'price' => 'required|numeric|min:0',
			'percent' => 'required|numeric|min:0|max:20',
		]);

		$plan = Plan::find($request->id);
		$planUpdate = $request->all();

		$planUpdate = $this->unsetEmpty($planUpdate);

		$plan->update($planUpdate);
	}

	public function editSeller(Request $request) {
		$request->validate([
			'first_name' => 'required',
			'last_name' => 'required',
			'seller_group_id' => 'required',
		]);

		$seller = Seller::find($request->id);
		$sellerUpdate = $request->all();

		if ($sellerUpdate['password']) {
			$sellerUpdate['password'] = bcrypt($sellerUpdate['password']);
		}

		$sellerUpdate = $this->unsetEmpty($sellerUpdate);

		$seller->update($sellerUpdate);
	}

	public function editSellerGroup(Request $request) {
		$request->validate([
			'name' => 'required',
		]);

		$sellerGroup = SellerGroup::find($request->id);
		$sellerGroupUpdate = $request->all();

		$sellerGroupUpdate = $this->unsetEmpty($sellerGroupUpdate);

		$sellerGroup->update($sellerGroupUpdate);
	}

	public function editSubscriber(Request $request) {
		$request->validate([
			'price' => 'required|numeric|min:0',
			'percent' => 'required|numeric|min:0|max:20',
			'subscribed_at' => 'required',
			'expires_at' => 'required',
		]);

		$subscription = Subscription::find($request->id);
		$subscriptionGroupUpdate = $request->all();

		$subscriptionGroupUpdate = $this->unsetEmpty($subscriptionGroupUpdate);

		$subscription->update($subscriptionGroupUpdate);
	}

	public function editUser(Request $request) {
		$request->validate([
			'first_name' => 'required',
			'last_name' => 'required',
		]);

		$user = User::find($request->id);
		$userUpdate = $request->all();

		if ($userUpdate['password']) {
			$userUpdate['password'] = bcrypt($userUpdate['password']);
		}

		$userUpdate = $this->unsetEmpty($userUpdate);

		$user->update($userUpdate);
	}

	public function unsetEmpty($Resource) {
		foreach ($Resource as $k => $v) {
			if (!isset($v)) {
				unset($Resource[$k]);
			}
		}
		return $Resource;
	}

}
