<?php

class UpdateCasesFromSCM implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public int $tries = 5;

	public int $companyId;
	public int $debtorId;

	//if a companyId comes, then it update all debtor with already send cases for this company.
	//if a debtorId comes, then it update only for this debtor.
	public function __construct($companyId = 0, $debtorId = 0) {
		$this->companyId = $companyId;
		$this->debtorId = $debtorId;
	}

	public function handle() {
		Log::info('start UpdateCasesFromSCM');

		try {
			if ($this->debtorId !== 0) {
				Log::info('UpdateCasesFromSCM for debtor id ' . $this->debtorId);
				$debtorsSendToSCM = Debtor::query()->where('id', $this->debtorId)->get();
			} elseif ($this->companyId !== 0) {
				Log::info('UpdateCasesFromSCM for company id ' . $this->companyId);
				$debtorsSendToSCM = Debtor::query()->where('company_id', $this->companyId)->whereNotNull('scm_case_id')->get();
			} else {
				$debtorsSendToSCM = Debtor::query()->whereNotNull('scm_case_id')->get();
			}

			foreach ($debtorsSendToSCM as $debtor) {
				$caseIdRequest = new ScmCaseStatusRequest();
				$caseIdRequest->FileId = $debtor->scm_case_id;
				$api = new ScmApi();
				$result = $this->extractResultData($api->caseStatus($caseIdRequest));
				$debtor->update([
					'scm_case_state' => $result->statusId,
					'scm_case_number' => $result->fileNumber,
					'scm_case_open' => $result->isOpen,
					'scm_case_state_update_date' => Carbon::now(),
				]);
			}

			return 'Success';
		} catch (\Exception $e) {
			$this->release(30);
			Log::error('UpdateCasesFromSCM Error message:' . $e->getMessage());
			return $e->getMessage();
		}
	}

	private function extractResultData(ScmApiResult $result) {
		if (!$result->isSuccesful()) {
			$result->logError();
			throw new \Exception('Update error. Got error response from SCM API <br>' . json_encode($result, JSON_PRETTY_PRINT));
		}

		Log::info('SCM Update Receiving: ' . json_encode($result->data));

		return isset($result->data->result) ? $result->data->result : $result->data->Result;
	}

}
