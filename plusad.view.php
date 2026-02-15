<?php
    
	//사용자 뷰

    class plusadView extends plusad {

        //초기화
        function init() {
            // 사용자 템플릿 파일의 경로 설정 (skins)
			$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            if(!is_dir($template_path)||!$this->module_info->skin) {
                $this->module_info->skin = 'default';
                $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            }
            $this->setTemplatePath($template_path);
        }
		
		
		//광고목록
		function dispPlusadlist(){
			
			//현재 페이지 가져옴
		    $args->page = Context::get('page');
			
			//목록 가져옴
			$oPlusadModel = getModel('plusad');
			$output = $oPlusadModel->getadlist($args);
			
			//목록 세팅
			Context::set('ad_list',$output->data);
			
			//페이지 세팅
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page_list', $output->data);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);
		
			//템플릿 지정
			$this->setTemplateFile('list');
		}
		
		//내광고목록
		function dispPlusadlistuser(){
			//권한확인
			if(!$this->grant->write) return new BaseObject(-1, '로그인후 이용가능합니다');
			
			//페이지 가져옴
		    $args->page = Context::get('page');
			
			//회원번호 가져옴
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
			
			//목록쿼리 날림
			$output = executeQuery('plusad.getadlistuser',$args);
			
			//목록 세팅
			Context::set('ad_list',$output->data);
			
			//페이지 세팅
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page_list', $output->data);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);
			
			//템플릿 지정
			$this->setTemplateFile('list_user');
		}
		
		
		//광고등록
		function dispPlusadwrite(){
			//등록권한확인
			if(!$this->grant->write) return new BaseObject(-1, '로그인후 이용가능합니다');
						
			//설정된 허용광고시간 세팅
			Context::set('adtime',explode(',',$this->module_info->adtime));
			
			//템플릿 지정
			$this->setTemplateFile('write');
		}
		
		//광고수정
		function dispPlusadUpdate(){
			//getUrl로 값을 넘겨받음
			$ad_srl = Context::get('ad_srl');
			$ad_content = Context::get('ad_content');
			$ad_url = Context::get('ad_url');
			
			//값을 템플릿으로 보내기 위해 세팅함
			Context::set('ad_srl',$ad_srl);
			Context::set('ad_content',$ad_content);
			Context::set('ad_url',$ad_url);
			
			//템플릿 지정
			$this->setTemplateFile('update');
		}
		
		//광고삭제
		function dispPlusaddelete(){
			//광고삭제
			$oPlusadModel = getModel('plusad');
			$output = $oPlusadModel->deletead();
			
			//광고목록으로 돌아감
			$this->setRedirectUrl(Context::get('success_return_url')); 
		}
    }
?>