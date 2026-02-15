<?php
    
	//관리자 컨트롤러

    class plusadAdminController extends plusad {

        //초기화
        function init() {           
        }

		//관리자 모듈 설정
		function procPlusadAdminstart(){
			
			//입력값을 모두 받음
            $args = Context::getRequestVars();
			$args->module = 'plusad';

			//모듈등록 유무에 따라 insert/update
			$oModuleController = getController('module');
			if(!$args->module_srl){
				$output = $oModuleController->insertModule($args); //모듈insert
				$this->setMessage('success_registed');
			}else{ 
				$output = $oModuleController->updateModule($args); //모듈update
				$this->setMessage('success_updated');
			}
            
			if(!$output->toBool()) return $output;
			
			//모듈시작 화면으로 돌아감
			$this->setRedirectUrl(getNotEncodedUrl('','module','admin','act','dispPlusadAdminstart')); 

		}
		
		
		
       
    }
?>