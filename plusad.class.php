<?php

/**
 * @class  plusad
 * @brief  PlusAd 광고등록 모듈 클래스
 */
class plusad extends ModuleObject
{
	/**
	 * @brief 설치시 추가 작업이 필요할시 구현
	 * @return BaseObject
	 */
	function moduleInstall()
	{
		return new BaseObject();
	}

	/**
	 * @brief 업데이트 필요여부 확인
	 * @return bool
	 */
	function checkUpdate()
	{
		// DB변동 사항 확인후 변동있을시 업데이트 버튼생성
		$oDB = DB::getInstance();

		// 컬럼 존재 여부 확인 // 13년12월5일 color컬럼추가 // ad_url 컬럼삭제후 재생성
		if (!$oDB->isColumnExists('plusad', 'color'))
		{
			return true;
		}

		return false;
	}

	/**
	 * @brief 업데이트 실행
	 * @return BaseObject
	 */
	function moduleUpdate()
	{
		// DB변동 사항 업데이트 진행
		$oDB = DB::getInstance();

		// 13년12월5일 color컬럼추가 // ad_url 컬럼삭제후 재생성
		if (!$oDB->isColumnExists('plusad', 'color'))
		{
			$oDB->addColumn('plusad', 'color', 'varchar', 20, '', true);
			$oDB->dropColumn('plusad', 'ad_url');
			$oDB->addColumn('plusad', 'ad_url', 'varchar', 250, '', true);
		}

		return new BaseObject(0, 'success_updated');
	}

	/**
	 * @brief 캐시 파일 재생성
	 * @return void
	 */
	function recompileCache()
	{
	}
}