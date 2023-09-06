<?php

class IntegrationLogic {

	public function afterDeleteIntegration($companyId) {
		$reminderSystemService = resolve('App\Logic\Reminders\ReminderSystemService');
		$companyDebtors = Debtor::query()->where('company_id', '=', $companyId)->get();
		foreach ($companyDebtors as $debtor) {
			if ($debtor->collection_sent_date) continue;
			if ($debtor->is_process_started || $debtor->reminder_email_sending_date
				|| $debtor->second_reminder_email_sending_date || $debtor->third_reminder_email_sending_date) {
				$reminderSystemService->stopReminderSystem($debtor);
				continue;
			}
			$debtCollections = DebtCollection::query()->where('debtor_id', '=', $debtor->id)->withTrashed()->with('claims')->get();
			foreach ($debtCollections as $debtCollection) {
				foreach ($debtCollection->claims as $claim) {
					$claim->delete();
				}
				$debtCollection->forceDelete();
			}
			$debtor->delete();
		}
		Company::find($companyId)->update(['integration_status' => 'unconnected']);
		if (env('APP_ENV') == 'production') {
			//#496 off
			//Artisan::call("debtors:send-debtor-about-cases-pause {$companyId}");
		}
	}

	public function updateEconomicStatus() {
		$integrations = Integration::query()->where('integration_partner_id', 1)->with('company')->get();
		Log::info('updateEconomicStatus Start');
		//economic
		try {
			foreach ($integrations as $integration) {
				$economic = new RestClient(config('services.economic.secret'), $integration->auth['token']);
				$response = $economic->request->get('self');
				$status = $response->isSuccess();

				$this->actionUpdateIntegrationStatus($status, $integration);
			}
		} catch (Exception $e) {
			Log::error($e->getMessage());
		}
		Log::info('updateEconomicStatus End');
	}

	public function updateDineroStatus() {
		$integrations = Integration::query()->where('integration_partner_id', 2)->with('company')->get();
		Log::info('updateDineroStatus Start');
		//dinero
		foreach ($integrations as $integration) {
			if (isset($integration->auth['token']) && isset($integration->auth['orgId'])) {
				$dineroApi = new DineroApi($integration->auth['token'], $integration->auth['orgId']);
				try {
					if (!$dineroApi->isAccessTokenValid()) {
						/*
						 * WARNING!!!
						*/
						// refresh_token should be used only with the further save of integration,
						// otherwise integration will break
						$newToken = $dineroApi->refreshToken();

						$auth = $integration->auth;
						$auth['token'] = $newToken->jsonSerialize();
						$integration->auth = $auth;
						$integration->save();
					}
					$this->actionUpdateIntegrationStatus(true, $integration);
				} catch (\Exception $e) {
					Log::error('updateDineroStatus Error message:' . $e->getMessage());
					$this->actionUpdateIntegrationStatus(false, $integration);
				}
			} else {
				$this->actionUpdateIntegrationStatus(false, $integration);
			}
		}
		Log::info('updateDineroStatus End');
	}

	/**
	 * @param $status
	 * @param $integration
	 */
	public function actionUpdateIntegrationStatus($status, $integration) {
		if ($status) {
			if ($integration->company->integration_status != 'connected') {
				Company::find($integration->company->id)->update(["integration_status" => 'connected', "is_reminder_system_on" => 1]);
			}
		} else {
			switch ($integration->company->integration_status) {
				case 'error 1':
					Company::find($integration->company->id)->update(["integration_status" => 'error 2']);
					break;
				case 'error 2':
					Company::find($integration->company->id)->update(["integration_status" => 'error 3']);
					break;
				case 'error 3':
					Company::find($integration->company->id)->update(["integration_status" => 'error 4']);
					break;
				case 'error 4':
					Company::find($integration->company->id)->update(["integration_status" => 'error 5']);
					break;
				case 'error 5':
					break;
				default:
					Company::find($integration->company->id)->update(["integration_status" => 'error 1', "is_reminder_system_on" => 0]);
			}
			$debtors = Debtor::query()->where('company_id', '=', $integration->company->id)->get();
			if (!empty($debtors)) {
				$reminderSystemService = resolve('App\Logic\Reminders\ReminderSystemService');
				foreach ($debtors as $debtor) {
					if ($debtor->is_process_started) {
						$reminderSystemService->stopReminderSystem($debtor);
					}
				}
			}
		}
	}


}
