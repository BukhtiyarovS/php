<?php

class SCMController extends Controller {

	public function hello() {
		try {
			$api = new ScmApi();
			return $this->extractResultData($api->hello());
		} catch (\Throwable $e) {
			return $e->getMessage();
		}
	}

	public function caseStatus($id) {
		try {
			$caseStatusRequest = new ScmCaseStatusRequest();
			$caseStatusRequest->FileId = $id;
			$api = new ScmApi();
			$tt = $this->extractResultData($api->caseStatus($caseStatusRequest));
			return json_encode($tt, JSON_PRETTY_PRINT);
		} catch (\Throwable $e) {
			return $e->getMessage();
		}
	}

	public function labelsStatus($id) {
		try {
			$fileLabelsRequest = new ScmFileLabelsRequest();
			$fileLabelsRequest->FileId = $id;
			$api = new ScmApi();
			$tt = $this->extractResultData($api->fileLabels($fileLabelsRequest));
			return json_encode($tt, JSON_PRETTY_PRINT);
		} catch (\Throwable $e) {
			return $e->getMessage();
		}
	}

	public function updateAllForCompany($companyId) {
		$debtors = Debtor::query()->where('company_id', $companyId)->whereNotNull('scm_case_id')->get();
		try {
			foreach ($debtors as $debtor) {
				$fileLabelsRequest = new ScmFileLabelsRequest();
				$fileLabelsRequest->FileId = $debtor->scm_case_id;
				$api = new ScmApi();
				$tt = $this->extractResultData($api->fileLabels($fileLabelsRequest));
				return json_encode($tt, JSON_PRETTY_PRINT);
			}
		} catch (\Throwable $e) {
			return $e->getMessage();
		}
	}

	public function updateSCMForCasePage($companyId) {
		app(Dispatcher::class)->dispatchNow(new UpdateCasesFromSCM($companyId));
		app(Dispatcher::class)->dispatchNow(new UpdateLabelsFromSCM($companyId));
	}

	private function extractResultData(ScmApiResult $result) {
		if (!$result->isSuccesful()) {
			$result->logError();
			throw new \Exception('Got error response from SCM API <br>' . json_encode($result, JSON_PRETTY_PRINT));
		}

		Log::info('SCM Integration Receiving: ' . json_encode($result->data));

		return isset($result->data->result) ? $result->data->result : $result->data->Result;
	}

}
