<?php

class SendDebtcollectionClaimsMailFiles extends Mailable {
	use Queueable, SerializesModels;

	private $debtorCvr;
	private $company;
	private $debtor;
	private $plan;
	private $seller;
	private $debtCollection;
	private $plan_title;
	private $subscriptions;

	public $tries = 5;


	/**
	 * Create a new message instance.
	 *
	 * @return void
	 */
	public function __construct($debtCollection) {
		$this->debtCollection = $debtCollection;
		$this->company = Company::find($debtCollection->company_id);
		$this->debtor = Debtor::find($debtCollection->debtor_id);
		$this->plan = Plan::find($this->company->plan_id);
		$this->subscriptions = $this->company->getFirstSubscription();
		$this->seller = Seller::find($this->company->seller_id);
		$this->plan_title = $this->plan->name;
		if ($this->plan_title === 'Plus') {
			$this->plan_title .= ' 1 år';
		}
		if ($this->plan_title === 'Plus 2' || $this->plan_title === 'Plus 3') {
			$this->plan_title .= ' år';
		}
	}

	/**
	 * Build the message.
	 *
	 * @return $this
	 */
	public function build(DebtReminderService $debtReminderService) {
		Log::info('Start sending claim email to admin');

		$result = $this
			->markdown('emails.send-case-email-files')
			->subject(__('Case') . ' i SCM ' . ' - ' . $this->company->name . __('against') .
				$this->debtor->name . ' - ' . $this->plan_title . ' - ' . $this->subscriptions->percent . '% - Files');

		$errorHappened = false;

		if ($this->debtCollection->objection) {
			if ($this->debtCollection->objection_files) {
				$i = 1;
				foreach ($this->debtCollection->objection_files as $file) {
					$objectionPath = Claim::getStoragePath('objection', $this->company->id) . basename($file);
					$result->attachFromStorageDisk('s3', $objectionPath, 'objection_files ' . $i++);
				}
			}
		}

		// Claims collection\
		$claims = Claim::query()->where('debt_collection_id', $this->debtCollection->id)->get();
		foreach ($claims as $claim) {
			if ($claim->file == 'none' || !trim($claim->file)) continue;
			try {
				if (!Storage::disk('s3')->exists($claim->file)) {
					throw new \Exception('File not found on the storage: ' . $claim->file);
				} else {
					$result->attachFromStorageDisk('s3', $claim->file, $claim->getFriendlyFileName());
				}
			} catch (\Exception $e) {
				Log::error('Exception while trying to attach the file: ' . $e->getMessage());
				$errorHappened = true;
			}
		}

		return $result->with([
			'debtCollection' => $this->debtCollection,
			'company' => $this->company,
			'debtor' => $this->debtor,
			'plan' => $this->plan,
			'subscriptions' => $this->subscriptions,
			'seller' => $this->seller,
			'plan_title' => $this->plan_title,
			'errorHappened' => $errorHappened,
			'claims' => $claims,
		]);
	}
}
