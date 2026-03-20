<?php

/**
 * @class  plusadView
 * @brief  PlusAd 모듈 사용자 뷰 클래스
 */
class plusadView extends plusad
{
	/**
	 * @brief 초기화
	 * @return void
	 */
	function init()
	{
		// 사용자 템플릿 파일의 경로 설정 (skins)
		$template_path = sprintf("%sskins/%s/", $this->module_path, $this->module_info->skin);
		if (!is_dir($template_path) || !$this->module_info->skin)
		{
			$this->module_info->skin = 'default';
			$template_path = sprintf("%sskins/%s/", $this->module_path, $this->module_info->skin);
		}
		$this->setTemplatePath($template_path);
	}

	/**
	 * @brief 광고 목록
	 * @return void
	 */
	function dispPlusadlist()
	{
		// 현재 페이지 가져옴
		$args = new stdClass();
		$args->page = Context::get('page');

		// 목록 가져옴
		$oPlusadModel = plusadModel::getInstance();
		$output = $oPlusadModel->getadlist($args);

		// 목록 세팅
		Context::set('ad_list', $output->data);

		// 페이지 세팅
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page_list', $output->data);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		// 템플릿 지정
		$this->setTemplateFile('list');
	}

	/**
	 * @brief 내 광고 목록
	 * @return BaseObject|void
	 */
	function dispPlusadlistuser()
	{
		// 권한확인
		if (!$this->grant->write)
		{
			return new BaseObject(-1, '로그인후 이용가능합니다');
		}

		// 페이지 가져옴
		$args = new stdClass();
		$args->page = Context::get('page');

		// 회원번호 가져옴
		$logged_info = Context::get('logged_info');
		$args->member_srl = $logged_info->member_srl;

		// 목록쿼리 날림
		$output = executeQuery('plusad.getadlistuser', $args);

		// 목록 세팅
		Context::set('ad_list', $output->data);

		// 취소 가능 기준 비율 세팅 (기본 10%)
		$cancel_threshold = $this->module_info->cancel_threshold_percent ? intval($this->module_info->cancel_threshold_percent) : 10;
		Context::set('cancel_threshold_percent', $cancel_threshold);

		// 페이지 세팅
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page_list', $output->data);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		// 템플릿 지정
		$this->setTemplateFile('list_user');
	}

	/**
	 * @brief 광고 등록 폼
	 * @return BaseObject|void
	 */
	function dispPlusadwrite()
	{
		// 등록권한확인
		if (!$this->grant->write)
		{
			return new BaseObject(-1, '로그인후 이용가능합니다');
		}

		// 설정된 허용광고시간 세팅
		Context::set('adtime', explode(',', $this->module_info->adtime));

		// 템플릿 지정
		$this->setTemplateFile('write');
	}

	/**
	 * @brief 광고 수정 폼
	 * @return void
	 */
	function dispPlusadUpdate()
	{
		// getUrl로 값을 넘겨받음
		$ad_srl = Context::get('ad_srl');
		$ad_content = Context::get('ad_content');
		$ad_url = Context::get('ad_url');

		// 값을 템플릿으로 보내기 위해 세팅함
		Context::set('ad_srl', $ad_srl);
		Context::set('ad_content', $ad_content);
		Context::set('ad_url', $ad_url);

		// 템플릿 지정
		$this->setTemplateFile('update');
	}

	/**
	 * @brief 광고 삭제
	 * @return void
	 */
	function dispPlusaddelete()
	{
		// 광고삭제
		$oPlusadModel = plusadModel::getInstance();
		$oPlusadModel->deletead();

		// 광고목록으로 돌아감
		$this->setRedirectUrl(Context::get('success_return_url'));
	}

		/**
	 * @brief Process ad cancellation with partial refund
	 * @return BaseObject|void
	 */
	function dispPlusadCancel()
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
}