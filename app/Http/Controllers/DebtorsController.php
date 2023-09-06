<?php

class DebtorsController extends Controller {

	public function sendPotentialCustomer(int $value) {
		$debtors = Debtor::query()
			->where('is_send_to_seller', '=', 2)
			->get();
		foreach ($debtors as $debtor) {
			$sendId = $this->getIdForPotentialCustomerMail($debtor);
			if ($debtor && $sendId === $value && $debtor->is_send_to_seller == 2) {
				Mail::to('admin1@admin.admin')->queue((new SendDebtorToSeller($debtor))->onQueue('email'));
				$debtor->update(['is_send_to_seller' => 3]);
				break;
			}
		}
		return view('thank-you-message');
	}

	public function getIdForPotentialCustomerMail($debtor): int {
		$y = (int)Carbon::parse($debtor->created_at)->format('Y');
		$m = (int)Carbon::parse($debtor->created_at)->format('m');
		$d = (int)Carbon::parse($debtor->created_at)->format('d');
		return $y + $m + $d + $debtor->id;
	}
}
