<?php

/**
 * @class  plusadController
 * @author [Author Name]
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
		// TODO: Move this list to module configuration
		$whitelist = [
			'yeokka.com',
			'*.yeokka.com',
			'*.youtube.com',
			'chzzk.naver.com',
			'yeokka.kro.kr',
			'fanbinit.us',
			'*.fanbinit.us',
			'cafe.naver.com',
			'playeternalreturn.com',
			'pokerogue.net',
			'pokerogue.cc',
			'docs.google.com',
			'*.blizzard.com',
			'*.youtube.com',
			'youtu.be'
		];

		$result = [];
		foreach ($whitelist as $domain)
		{
			$result[] = str_replace('\*\.', '[a-z0-9-]+\.', preg_quote($domain, '%'));
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

		if (!$args->ad_url)
		{
			$args->ad_url = '';
		}

		// Check max allowed time
		if ($args->time > 128)
		{
			return new BaseObject(-1, '광고 허용시간을 초과하였습니다.');
		}

		// Check whitelist
		if (!$this->matchWhitelist($args->ad_url))
		{
			return new BaseObject(-1, '등록 불가능한 주소입니다');
		}

		// Check user points
		$oPointModel = pointModel::getInstance();
		$current_point = $oPointModel->getPoint($logged_info->member_srl);

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
			return new BaseObject(-1, '광고등록에 실패하였습니다.');
		}

		// Deduct points
		$oPointController = pointController::getInstance();
		$oPointController->setPoint($logged_info->member_srl, $args->ad_point, 'minus');

		// Set success message and redirect
		$this->setMessage('광고 등록 완료');
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
			return new BaseObject(-1, '광고수정에 실패하였습니다.');
		}

		// Set success message and redirect
		$this->setMessage('광고 수정 완료');
		$this->setRedirectUrl(getNotEncodedUrl('act', 'dispPlusadlist'));
	}
}