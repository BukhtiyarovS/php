<?php

class DeleteResourceController extends Controller {

	private function deleteMain($resources) {
		if (!Auth::user()->siteAdmin()) {
			abort(403, 'Unauthorized action.');
		}
		try {
			if ($resources) {
				$resources->delete();
				return response()->json([
					'response' => true,
					'type' => true,
				]);
			}
			return response()->json([
				'response' => false,
				'type' => 'Resources not found',
			]);
		} catch (\Throwable $e) {
			Log::error('Error message:' . $e->getMessage());
			return response()->json([
				'response' => false,
				'type' => $e->getMessage(),
			]);
		}
	}

	public function deleteBetaUser($id) {
		$resources = BetaUser::find($id);
		return $this->deleteMain($resources);
	}

	public function deleteCase($id) {
		$resources = DebtCollection::findOrFail($id);
		return $this->deleteMain($resources);
	}

	public function deleteCompany($id) {
		$resources = Company::find($id);
		return $this->deleteMain($resources);
	}

	public function deleteCompanyTodo($id) {
		$resources = CompanyTodo::find($id);
		return $this->deleteMain($resources);
	}

	public function deleteLead($id) {
		$resources = Lead::find($id);
		return $this->deleteMain($resources);
	}

	public function deleteNews($id) {
		$resources = News::find($id);
		return $this->deleteMain($resources);
	}

	public function deleteSeller($id) {
		$seller = User::find($id);
		try {
			if ($seller) {

				$seller->is_seller = false;
				$seller->save();
				return response()->json([
					'response' => true,
					'type' => true,
				]);
			}
			return response()->json([
				'response' => false,
				'type' => 'Seller not found',
			]);
		} catch (\Throwable $e) {
			Log::error('Error message:' . $e->getMessage());
			return response()->json([
				'response' => false,
				'type' => $e->getMessage(),
			]);
		}
	}

}
