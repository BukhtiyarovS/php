<?php

class SendCreateOrUpdateCaseMail extends Mailable {
	use Queueable, SerializesModels;

	public $company;
	public $debtor;
	public $plan;
	public $seller;
	public $debtCollection;
	public $plan_title;

	public $tries = 1;
	public $subscriptions;


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
	public function build() {
		return $this->markdown('emails.send-create-or-update-case-email')
			->subject(__('Case') . ' i SCM ' . ' - ' . $this->company->name . __('against') .
				$this->debtor->name . ' - ' . $this->plan_title . ' - ' . $this->subscriptions->percent . '%')
			->with([
				'company' => $this->company,
				'debtor' => $this->debtor,
				'plan' => $this->plan,
				'subscriptions' => $this->subscriptions,
				'seller' => $this->seller,
				'plan_title' => $this->plan_title,
			]);
	}
}
