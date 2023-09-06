<?php

class ViewResourceController extends Controller {

	public function getBetaUserById($id) {
		return BetaUser::find($id);
	}

	public function getBookkeeperById($id) {
		return Bookkeeper::query()->where('id', '=', $id)->with('companies')->first();
	}

	public function getClaimById($id) {
		$claim = Claim::query()->where('id', '=', $id)->with('debtcollection')->first();
		$claim['debt_collection'] = DebtCollection::query()->where('id', '=', $claim->debt_collection_id)->withTrashed()->first();
		return $claim;
	}

	public function getCompanyById($id) {
		$company = Company::query()->where('id', '=', $id)
			->with('user')
			->with('users')
			->with('debtors')
			->with('debtcollections')
			->with('company_todo')
			->with('integrations')
			->with('plan')
			->first();
		$company['subscription'] = $company->getCurrentSubscription();
		return $company;
	}

	public function getCompanyTodoById($id) {
		return CompanyTodo::query()->where('id', '=', $id)->with('company')->with('seller')->first();
	}

	public function getDebtCollectionById($id) {
		$debtCollection = DebtCollection::query()->where('id', '=', $id)->with('company')->with('debtor')->with('claims')->first();
		$debtCollection['status_types'] = $debtCollection->getStatusTypes();
		return $debtCollection;
	}

	public function getDebtorById($id) {
		return Debtor::find($id);
	}

	public function getPartnerById($id) {
		return IntegrationPartner::find($id);
	}

	public function getLeadById($id) {
		$lead = Lead::find($id);
		if ($lead->companies) {
			foreach ($lead->companies as $company) {
				$company['subscription'] = $company->getCurrentSubscription();
			}
		}
		return $lead;
	}

	public function getNewsById($id) {
		return News::find($id);
	}

	public function getPlanById($id) {
		return Plan::find($id);
	}

	public function getSellerById($id) {
		return Seller::query()->where('id', '=', $id)->with('sellerGroup')->first();
	}

	public function getSellerGroupById($id) {
		return SellerGroup::find($id);
	}

	public function getSubscriberById($id) {
		return Subscription::query()->where('id', '=', $id)->with('company')->with('plan')->first();
	}

	public function getUserById($id) {
		return User::find($id);
	}

	public function getActionEventById($id) {
		return ActionEvents::find($id);
	}
}
