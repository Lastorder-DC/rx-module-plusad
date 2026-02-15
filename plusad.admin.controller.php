<?php

/**
 * @class  plusadAdminController
 * @brief  PlusAd 모듈 관리자 컨트롤러 클래스
 */
class plusadAdminController extends plusad
{
	/**
	 * @brief 초기화
	 * @return void
	 */
	function init()
	{
	}

	/**
	 * @brief 관리자 모듈 설정 처리
	 * @return BaseObject|void
	 */
	function procPlusadAdminstart()
	{
		// 입력값을 모두 받음
		$args = Context::getRequestVars();
		$args->module = 'plusad';

		// 모듈등록 유무에 따라 insert/update
		$oModuleController = moduleController::getInstance();
		if (!$args->module_srl)
		{
			$output = $oModuleController->insertModule($args);
			$this->setMessage('success_registed');
		}
		else
		{
			$output = $oModuleController->updateModule($args);
			$this->setMessage('success_updated');
		}

		if (!$output->toBool())
		{
			return $output;
		}

		// 모듈시작 화면으로 돌아감
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPlusadAdminstart'));
	}
}