<?php

class SendInviteToUser extends Mailable {
	use Queueable, SerializesModels;

	public $user;
	public $company;
	public $email = "";
	public $isNew = true;
	public $tries = 5;
	public $inviteObj = null;

	/**
	 * Create a new message instance.
	 *
	 * @return void
	 */
	public function __construct($email, $company, $isNew, $inviteObj) {
		$this->user = Auth::user();
		$this->email = $email;
		$this->company = $company;
		$this->isNew = $isNew;
		$this->inviteObj = $inviteObj;
	}

	/**
	 * Build the message.
	 *
	 * @return $this
	 */
	public function build() {
		return $this
			->markdown('emails.send-invite-to-company')
			->subject(__("Invitation til CloudCollect"))
			->with([
				'locale' => app()->getLocale(),
				'user' => $this->user,
				'company' => $this->company->name,
				'link' => $this->buttonLink(),
				'decline' => $this->declineLink(),
			]);
	}

	public function buttonLink() {
		if ($this->isNew) {
			return "/register?email=" . $this->email;
		} else {
			return "/dashboards";
		}
	}

	public function declineLink() {
		return "/users/decline/invite/" . $this->inviteObj->id;
	}
}
