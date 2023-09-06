<?php

class UserListController extends Controller {

	public function redirect() {
		log::info('redirect back');
		return redirect()->back();
	}

	public function userList() {
		$company = Auth::company();
		$authUser = Auth::user();
		// $company->unsetRelation('users');
		$users = $company->users->map(function ($user) use ($authUser, $company) {
			$user->isOwner = $user->id == $company->owner_id;
			$user->isMe = $authUser->id == $user->id;
			$user->isAdmin = $user->pivot->isAdmin;
			$user->isBookkeeper = $user->pivot->isBookkeeper;

			return $user;
		});
		return $users;
	}

	public function updateOverdueNotification(Request $request, $id) {
		$company = Auth::company();
		$companyUser = CompanyUser::where('company_id', $company->id)->where('user_id', $id);

		$companyUser->update(
			$request->all()
		);
	}

	public function getAvailableOverdueDisplays() {
		return ReminderFlowFactory::getAvailableOverdueDisplays();
	}

	public function impersonate(User $user) {
		if (session()->has('impersonated_by')) {
			return redirect('/403');
		}
		Session::put('AUTH_USER', $user);
		Session::put('ACTIVE_COMPANY', '');
		Auth::user()->impersonate($user);
	}

	public function impersonateLeav() {
		Auth::user()->leaveImpersonation();
		Session::put('ACTIVE_COMPANY', '');
		return redirect('/');
	}

}
