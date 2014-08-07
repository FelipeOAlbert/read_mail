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
            
            if (!$this->conexao) {
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
                    $emails[$i]['corpo'] =  imap_qprint(imap_body($this->conexao, $i));
                    
                    $mailStruct     = imap_fetchstructure($this->conexao, $i);
                    $attachments    = $this->getAttachments($this->conexao, $i, $mailStruct, "");
                    
                    $emails[$i]['anexo'] = $attachments;
                }
                
                $this->printr($emails);
            }
        }
        
        function getAttachments($imap, $mailNum, $part, $partNum) {
            $attachments = array();
         
            if (isset($part->parts)) {
                foreach ($part->parts as $key => $subpart) {
                    if($partNum != "") {
                        $newPartNum = $partNum . "." . ($key + 1);
                    }
                    else {
                        $newPartNum = ($key+1);
                    }
                    $result = $this->getAttachments($imap, $mailNum, $subpart,
                        $newPartNum);
                    if (count($result) != 0) {
                         array_push($attachments, $result);
                     }
                }
            }
            else if (isset($part->disposition)) {
                if ($part->disposition == "ATTACHMENT") {
                    $partStruct = imap_bodystruct($imap, $mailNum,
                        $partNum);
                    $attachmentDetails = array(
                        "name"    => $part->dparameters[0]->value,
                        "partNum" => $partNum,
                        "enc"     => $partStruct->encoding
                    );
                    return $attachmentDetails;
                }
            }
         
            return $attachments;
        }
        
        function corpo_mensagem($uid, $mime_type)
        {
            return $this->get_part($uid, $mime_type);
        }
        
        function get_part($uid, $mimetype, $structure = false, $partNumber = false) {
            if (!$structure) {
                   $structure = imap_fetchstructure($this->conexao, $uid, FT_UID);
            }
            if ($structure) {
                if ($mimetype == $this->get_mime_type($structure)) {
                    if (!$partNumber) {
                        $partNumber = 1;
                    }
                    $text = imap_fetchbody($this->conexao, $uid, $partNumber, FT_UID);
                    switch ($structure->encoding) {
                        case 3: return imap_base64($text);
                        case 4: return imap_qprint($text);
                        default: return $text;
                   }
               }
         
                // multipart 
                if (isset($structure->type) and $structure->type == 1) {
                    foreach ($structure->parts as $index => $subStruct) {
                        $prefix = "";
                        if ($partNumber) {
                            $prefix = $partNumber . ".";
                        }
                        $data = $this->get_part($this->conexao, $uid, $mimetype, $subStruct, $prefix . ($index + 1));
                        if ($data) {
                            return $data;
                        }
                    }
                }
            }
            return false;
        }
         
        function get_mime_type($structure) {
            $primaryMimetype = array("TEXT", "MULTIPART", "MESSAGE", "APPLICATION", "AUDIO", "IMAGE", "VIDEO", "OTHER");
            
            if (isset($structure->subtype)) {
               return $primaryMimetype[(int)$structure->type] . "/" . $structure->subtype;
            }
            
            return "TEXT/PLAIN";
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
        
        function lista_email($pasta)
        {
            //$caixa = imap_open($this->servidor.'['.$pasta.']', $this->login, $this->senha);
            
            $caixa = imap_open($this->servidor, $this->login, $this->senha);
            
            //$check = imap_mailboxmsginfo($caixa);
            
            //echo 'aki';
            //$mensagens = imap_check($check);
           
            
            
           
           // Fetch an overview for all messages in INBOX
           $result = imap_fetch_overview($caixa, 1);
           
           
           
           foreach ($result as $overview) {
               echo "#{$overview->msgno} ({$overview->date}) - From: {$overview->from}
               {$overview->subject}<br>";
           }
           imap_close($caixa);
            
            die('eteste');
            
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