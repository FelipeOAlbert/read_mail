<?php

    if (!extension_loaded('imap')) {
        die('Modulo PHP/IMAP nao foi carregado');
    }

    class read_mail{
        
        private $servidor, $login, $senha;
        public $conexao;
        
        function __construct ()
        {
            $this->servidor = '{imap.gmail.com:993/imap/ssl}';
            $this->login    = '';
            $this->senha    = '';
            
            $this->conecta_email();
            $this->salva_email();
        }
        
        function conecta_email()
        {
            $this->conexao = imap_open($this->servidor, $this->login, $this->senha);
            
            if (!$this->conexao){
                die('Erro ao conectar: '.imap_last_error());
            }
        }
        
        function salva_email()
        {
            $check = imap_check($this->conexao);
            
            $emails = array();
            
            if($check){
                
                for($i = 1; $i <= $check->Nmsgs; $i++){
                    
                    $overview = imap_fetch_overview($this->conexao, $i);
                    
                    $email = current($overview);
                    
                    // Assunto
                    $emails[$i]['assunto'] = $email->subject;
                    
                    // Remetente
                    $emails[$i]['remetente'] = $email->from;
                    
                    // Data
                    $emails[$i]['data'] = $email->date;
                    
                    // Identificador da mensagem
                    $emails[$i]['message_id'] =  $email->message_id;
                    
                    // Identificador da mensagem na caixa de entrada
                    $emails[$i]['uid'] =  $email->uid;
                    
                    // corpo da mensagem
                    $emails[$i]['corpo'] =  quoted_printable_decode(imap_body($this->conexao, $i));
                    
                    $this->downloadAnexo($i, $email->uid);
                }
                
                $this->printr($emails);
            }
        }
        
        function downloadAnexo($id, $uid)
        {
            $structure = imap_fetchstructure($this->conexao, $id);
            
            $attachments = array();
            
            if(isset($structure->parts) && count($structure->parts)){
                
                for($i = 0; $i < count($structure->parts); $i++){
                    
                    $attachments[$i] = array(
                        'is_attachment' => false,
                        'filename'      => '',
                        'name'          => '',
                        'attachment'    => ''
                    );
                    
                    if($structure->parts[$i]->ifdparameters){
                        
                        foreach($structure->parts[$i]->parameters as $object){
                            
                            if(strtolower($object->attribute) == 'filename'){
                                $attachments[$i]['is_attachment']   = true;
                                $attachments[$i]['filename']        = $object->value;
                            }
                        }
                    }
                    
                    if($structure->parts[$i]->ifparameters){
                        
                        foreach($structure->parts[$i]->parameters as $object){
                            
                            if(strtolower($object->attribute) == 'name'){
                                $attachments[$i]['is_attachment']   = true;
                                $attachments[$i]['name']            = $object->value;
                            }
                        }
                    }
                    
                    if($attachments[$i]['is_attachment']){
                        
                        $attachments[$i]['attachment'] = imap_fetchbody($this->conexao, $id, $i+1);
                        
                        /* 4 = QUOTED-PRINTABLE encoding */
                        if($structure->parts[$i]->encoding == 3){
                            $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                        }
                        /* 3 = BASE64 encoding */
                        elseif($structure->parts[$i]->encoding == 4){ 
                            $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                        }
                    }
                }
            }
            
            /* salvando na pasta*/
            foreach($attachments as $attachment){
                
                if($attachment['is_attachment'] == 1){
                    
                    $filename = $attachment['name'];
                    if(empty($filename)) $filename = $attachment['filename'];
                    
                    if(empty($filename)) $filename = time() . ".dat";
                    
                    file_put_contents('attachment/'.$uid . "-" . $filename, $attachment['attachment']);
                }
            }
        }
        
        function lista_caixa($todas = false)
        {
            $pastas     = array();
            $marcadores = imap_getmailboxes($this->conexao, $this->servidor, '*');
            
            $count = 0;
            
            if (is_array($marcadores)) {
                foreach ($marcadores as $marcador) {
                    
                    $nome   = str_replace($this->servidor, '', $marcador->name);
                    $pos    = strpos($nome, $marcador->delimiter);
                    
                    if ($pos !== false) {
                        $pastas[] = substr($nome, $pos + 1);
                    }elseif($todas !== false){
                        $pastas[] = $nome;
                    }
                }
            }else{
                die(imap_last_error());
            }
            
            return $pastas;
        }
        
        function printr($data)
        {
            print "<pre>";
            print_r($data);
            die();
        }
        
        function __destruct()
        {
            imap_close($this->conexao);
        }
    }
    
    $read = new read_mail();
?>