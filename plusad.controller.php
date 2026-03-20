<?php

/**
 * @class  plusadController
 * @brief  PlusAd module user controller class
 */
class plusadController extends plusad
{
	/**
	 * @brief Initialization
	 */
	function init()
	{
	}

	/**
	 * @brief Get whitelist regex pattern
	 * @return string Regex pattern
	 */
	function getWhitelistRegex()
	{
		$whitelist = [];
		if($this->module_info->domain_list)
		{
			$whitelist = explode("\n", str_replace("\r", "", $this->module_info->domain_list));
		}

		$result = [];
		foreach ($whitelist as $domain)
		{
			$domain = trim($domain);
			if(!$domain)
			{
				continue;
			}
			$result[] = str_replace('\*', '[-a-zA-Z0-9._]*', preg_quote($domain, '%'));
		}

		if(!count($result))
		{
			return '/^$/';
		}

		return '%^(?:https?:)?//(' . implode('|', $result) . ')%';
	}

	/**
	 * @brief Check if the URL matches the whitelist
	 * @param string $url URL to check
	 * @return bool Returns true if matched or empty, false otherwise
	 */
	function matchWhitelist(string $url)
	{
		if (!$url)
		{
			return true; // Allow if URL is empty
		}
		return preg_match($this->getWhitelistRegex(), $url) ? true : false;
	}

	/**
	 * @brief Process ad registration
	 * @return BaseObject|void
	 */
	function procPlusadwrite()
	{
		// Get request variables
		$args = Context::getRequestVars();
		$args->module = 'plusad';

		$logged_info = Context::get('logged_info');
		$args->member_srl = $logged_info->member_srl;
		$args->nick_name = $logged_info->nick_name;
		$args->regdate = date('Y-m-d H:i:s');
		$args->enddate = date('Y-m-d H:i:s', strtotime('+' . $args->time . 'hours'));

		// Calculate points
		$minus_point = $args->time * $this->module_info->adpoint; // Time based point
		$bold_point = ($args->bold == 'yes') ? $this->module_info->boldpoint : 0; // Bold effect point
		$color_point = ($args->color != 'no') ? $this->module_info->colorpoint : 0; // Color effect point
		$args->ad_point = $minus_point + $bold_point + $color_point;

		// Set ad URL if null
		if (!$args->ad_url)
		{
			$args->ad_url = '';
		}

		// Check max allowed time
		if ($args->time > 128)
		{
			return new BaseObject(-1, '광고 허용시간을 초과하였습니다.');
		}

		// Check min allowed time
		if ($args->time <= 0)
		{
			return new BaseObject(-1, '잘못된 광고 시간입니다.');
		}

		// Check whitelist
		if (!$this->matchWhitelist($args->ad_url))
		{
			return new BaseObject(-1, '등록 불가능한 주소입니다');
		}

		// Check user points
		$current_point = pointModel::getPoint($logged_info->member_srl);
		if ($args->ad_point > $current_point)
		{
			return new BaseObject(-1, '포인트가 부족합니다');
		}

		// Calculate total accumulated points for the user
		$point_output = executeQuery('plusad.getadpoint', $args);
		$member_point = 0;
		if ($point_output->data)
		{
			foreach ($point_output->data as $val => $point)
			{
				$member_point = $point;
			}
		}
		$args->total_point = $member_point + $args->ad_point;

		// Insert ad into DB
		$output = executeQuery("plusad.insert_ad", $args);
		if (!$output->toBool())
		{
			return new BaseObject(-1, '광고 등록에 실패하였습니다.');
		}

		// Deduct points
		pointController::setPoint($logged_info->member_srl, $args->ad_point, 'minus');

		// Set success message and redirect
		$this->setMessage('광고 등록 완료');
		$this->setRedirectUrl(Context::get('success_return_url'));
	}

	/**
	 * @brief Process ad cancellation with partial refund
	 * @return BaseObject|void
	 */
	function procPlusadCancel()
	{
		$ad_srl = Context::get('ad_srl');
		$logged_info = Context::get('logged_info');

		if (!$logged_info)
		{
			return new BaseObject(-1, '로그인후 이용가능합니다');
		}

		// Fetch ad info
		$oPlusadModel = plusadModel::getInstance();
		$ad_info = $oPlusadModel->getad($ad_srl);
		if (!$ad_info)
		{
			return new BaseObject(-1, '광고 정보를 찾을 수 없습니다.');
		}

		// Verify ownership
		if ($ad_info->member_srl != $logged_info->member_srl)
		{
			return new BaseObject(-1, '본인의 광고만 취소할 수 있습니다.');
		}

		// Calculate elapsed time percentage
		$regdate_time = strtotime($ad_info->regdate);
		$enddate_time = strtotime($ad_info->enddate);
		$now = time();
		$total_seconds = $enddate_time - $regdate_time;
		$elapsed_seconds = $now - $regdate_time;

		if ($total_seconds <= 0 || $elapsed_seconds < 0)
		{
			return new BaseObject(-1, '광고 시간 정보가 올바르지 않습니다.');
		}

		$elapsed_percent = round(($elapsed_seconds / $total_seconds) * 100);

		// Get cancel threshold and fee from settings (default 10%)
		$cancel_threshold = $this->module_info->cancel_threshold_percent ? intval($this->module_info->cancel_threshold_percent) : 10;
		$cancel_fee = $this->module_info->cancel_fee_percent ? intval($this->module_info->cancel_fee_percent) : 10;

		// Check if within cancellation window
		if ($elapsed_percent >= $cancel_threshold)
		{
			return new BaseObject(-1, sprintf('광고 등록 후 전체 시간의 %d%% 이상 경과하여 취소할 수 없습니다.', $cancel_threshold));
		}

		// Calculate refund: ad_point minus elapsed% minus fee%
		$deduction_percent = $elapsed_percent + $cancel_fee;
		$refund_point = max(0, intval($ad_info->ad_point * (100 - $deduction_percent) / 100));

		// Delete the ad
		$args = new stdClass();
		$args->ad_srl = $ad_srl;
		$output = executeQuery('plusad.deletead', $args);
		if (!$output->toBool())
		{
			return new BaseObject(-1, '광고 취소에 실패하였습니다.');
		}

		// Refund points
		if ($refund_point > 0)
		{
			pointController::setPoint($logged_info->member_srl, $refund_point, 'add');
		}

		// Set success message and redirect
		$this->setMessage(sprintf('광고가 취소되었습니다. %s 포인트가 환불되었습니다.', number_format($refund_point)));
		$this->setRedirectUrl(Context::get('success_return_url'));
	}

	/**
	 * @brief Process ad update
	 * @return BaseObject|void
	 */
	function procPlusadUpdate()
	{
		// Get request variables
		$args = Context::getRequestVars();

		// Update DB
		$output = executeQuery("plusad.update_ad", $args);
		if (!$output->toBool())
		{
			return new BaseObject(-1, '광고 수정에 실패하였습니다.');
		}

		// Set success message and redirect
		$this->setMessage('광고 수정 완료');
		$this->setRedirectUrl(getNotEncodedUrl('act', 'dispPlusadlist'));
	}
}