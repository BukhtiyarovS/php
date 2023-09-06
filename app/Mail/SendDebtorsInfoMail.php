<?php

class SendDebtorsInfoMail extends Mailable {
	use Queueable, SerializesModels;

	public $data;

	/**
	 * Create a new message instance.
	 *
	 * @return void
	 */
	public function __construct($data) {
		$this->data = $data;
	}

	/**
	 * Build the message.
	 *
	 * @return $this
	 */
	public function build() {
		return $this->view('emails.send-debtors-info')
			->subject(__('mail.send-debtors-info-mail'))
			->with([
				'locale' => $this->data['user_locale']
			]);
	}
}
