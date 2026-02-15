<?php
    
	//관리자 뷰

    class plusadAdminView extends plusad {

        //초기화
        function init() {
		 
			//모듈정보구함
			$oModuleModel = getModel('module');
			$oPlusadModel = getModel('plusad');
            $this->module_info = $oPlusadModel->getplusadinfo();
            $this->module_config = $oModuleModel->getModuleConfig('plusad');		
			//모듈정보세팅
			Context::set('module_config', $this->module_config);
            Context::set('module_info', $this->module_info);
			
            // 관리자 템플릿 파일의 경로 설정 (tpl)
            $template_path = sprintf("%stpl/",$this->module_path);
            $this->setTemplatePath($template_path);
        }
		
		//관리자 모듈 설정
		function dispPlusadAdminstart(){
						
			// 모듈 카테고리 목록 구함
			$oModuleModel = getModel('module');
            $module_category = $oModuleModel->getModuleCategories();
            Context::set('module_category', $module_category);
			
			// 스킨 목록 구함
            $skin_list = $oModuleModel->getSkins($this->module_path);
            Context::set('skin_list',$skin_list);
 
            // 레이아웃 목록 구함
            $oLayoutModel = getModel('layout');
            $layout_list = $oLayoutModel->getLayoutList();
            Context::set('layout_list', $layout_list);
			
			//템플릿 파일지정
			$this->setTemplateFile('index');
		}
		
				
		
		//광고로그
		function dispPlusadAdminlog(){
			$args->page = Context::get('page');
			$output = executeQuery('plusad.getadlistall',$args);
			
			//결과값 세팅
			Context::set('ad_list',$output->data);
			
			//페이지 세팅
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page_list', $output->data);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);
			
			//템플릿 파일 지정
			$this->setTemplateFile('log');
		}
		
		//광고로그삭제 (개별)
		function dispPlusadAdminlogdelete(){
			$oPlusadModel = getModel('plusad');
			$output = $oPlusadModel->deleteadponit();
			//이전화면으로 돌아가기
			$this->setRedirectUrl(Context::get('success_return_url')); 
		}
		
		//광고로그삭제 (전체)
		function dispPlusadAdminlogdeleteall(){
			$oPlusadModel = getModel('plusad');
			$output = $oPlusadModel->deleteadall();
			//이전화면으로 돌아가기
			$this->setRedirectUrl(Context::get('success_return_url')); 
		}
		
		//광고로그삭제 (진행종료된 광고)
		function dispPlusadAdminlogdeletelast(){
			$oPlusadModel = getModel('plusad');
			$output = $oPlusadModel->deleteadlast();
			//이전화면으로 돌아가기
			$this->setRedirectUrl(Context::get('success_return_url')); 
		}
		
		//스킨관리 
		function dispPlusadAdminskininfo() {
			$oModuleAdminModel = &getAdminModel('module');
			$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
			Context::set('skin_content', $skin_content);

			// 템플릿 파일 지정			
			$this->setTemplateFile('skin_info');
        }	
		
		//권한관리
		function dispPlusadAdmingrantset() {
			$oModuleAdminModel = &getAdminModel('module');
			$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
			Context::set('grant_content', $grant_content);
			
			//템플릿 파일 지정
			$this->setTemplateFile('grant_list');
		}
       
    }
?>