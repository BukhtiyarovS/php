<?php

class ClearPaidJob implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public function handle() {
		try {
			log::info('Start All Debt Collection status with paid claims update to open!');
			$debtCollection = DebtCollection::query()->where('status', 'paid')->withTrashed()->get();
			foreach ($debtCollection as $debt) {
				$debt->update(['status' => 'open']);
			}
			log::info('End All Debt Collection status with paid claims update to open!');
		} catch (\Exception $exception) {
			Log::error('ClearPaidJob Error: ' . $exception->getMessage());
			Log::info('---end ClearPaidJob job----');
		}

	}
}
