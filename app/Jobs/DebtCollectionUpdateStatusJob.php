<?php

class DebtCollectionUpdateStatusJob implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public function handle() {
		try {
			log::info('Start All Debt Collection status without claims update to paid!');
			$debtCollection = DebtCollection::query()->where('status', '=', 'open')->get();
			foreach ($debtCollection as $debt) {
				$isReminder = 0;
				$isPaid = 1;
				if (!count($debt['claims'])) {
					$this->updateDebtCollectionStatus($debt, 'closed');
					continue;
				}
				foreach ($debt['claims'] as $claim) {
					if (str_starts_with($claim['type'], 'reminder')) {
						$isReminder = 1;
					} else {
						$isPaid = 0;
					}
				}

				if ($isPaid && $isReminder) {
					$this->updateDebtCollectionStatus($debt, 'paid');
				}
			}
			log::info('End All Debt Collection status without claims update to paid!');
		} catch (\Exception $exception) {
			Log::error('DebtCollectionUpdateStatusJob Error: ' . $exception->getMessage());
			Log::info('---end DebtCollectionUpdateStatusJob job----');
		}
	}

	public function updateDebtCollectionStatus($debt, $status) {
		$newDebtCollection = DebtCollection::find($debt['id']);
		$newDebtCollection->update(['status' => $status]);
	}
}
