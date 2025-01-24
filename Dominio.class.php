<?php 

/**
 * Classe responsável por retornar o domínio de uma URL usando uma lista pública de sulfixos (PSL).
 * Recomendação: deixar a classe pré carregada para o sistema, visto que a leitura do arquivo de PSLs leva em torno de 40ms por vez, o que pode causar problemas de performance.
 */
class Dominio {

    public array $listaTlds;
    private string $caminhoNuvemPsl = 'https://publicsuffix.org/list/public_suffix_list.dat';

    public function __construct(){
        if(file_exists('psl-processado.bin')){
            $this->listaTlds = $this->processarTlds();
        }else{
            echo 'Warning: execute o método build() para carregar a classe' . PHP_EOL;
        }
    }

    /**
     * Função responsável por montar os arquivos PSL para que a classe funcione corretamente. Recomendado executar uma vez por semana.
     * NÃO execute todas as vezes, por baixar o arquivo da internet e fazer pré processamentos, é custoso e só deve ser executado quando necessário.
     * @return void
     */
    public function build(){
        if($this->baixarPsl()){
            $this->limparPsl();
            $this->preProcessarTlds();
            $this->limparArquivosTemporarios();
        }else{
            echo 'Ocorreu um erro ao baixar o arquivo das PSLs do link: ' . $this->caminhoNuvemPsl;
        }
    }

    /**
     * Método responsável por obter o domínio de uma URL
     * @param string $url
     * @return string Domínio da URL, sem subdomínios, protocolos, caminho e outros.
     */
    public function getDominio(string $url){
        if($url === '') return '';

        $url = strtolower($url);
        $url = $this->removerProtocolo($url);
        $url = $this->removerCaminho($url);
        $url = $this->removerWww($url);
    
        $url = explode('.', $url);
        $url = array_reverse($url);
    
        $aux = &$this->listaTlds;
        $dominio = [];
        foreach($url as $parte){
            $existe = isset($aux[$parte]);
    
            if($existe){
                $dominio[] = $parte;
                $aux = &$aux[$parte];
            }
        }
    
        if(isset($url[count($dominio)])) $dominio[] = $url[count($dominio)];
        $dominio = array_reverse($dominio);
    
        $dominio = implode('.', $dominio);
        return $dominio;
    }

    /**
     * Remove Http e Https de uma URL;
     * @param string $url
     * @return string Retorna a URL sem o protocolo
     */
    private function removerProtocolo($url){
        $url = str_replace('https://', '', $url);
        $url = str_replace('http://', '', $url);
    
        return $url;
    }
    
    /**
     * Remove o caminho interno do servidor de uma URL. Exemplo: www.google.com/algum-caminho-aqui/outro-caminho-aqui
     * @param string $url URL a ser limpa
     * @return string URL sem o caminho interno do servidor
     */
    private function removerCaminho($url){
        $chars = mb_str_split($url);
        $pathStartsAt = 0;
        foreach($chars as $index => $char){
            if($char === '/'){
                $pathStartsAt = $index;
                break;
            }
        }
    
        return $pathStartsAt > 0 ? substr($url, 0 , $pathStartsAt) : $url;
    }

    /**
     * Responsável por filtrar a palavra www dos domínios de URLs
     * @param mixed $url
     */
    function removerWww($url){
        if(strlen($url) < 4) return $url;
    
        $tresPrimeirosCaracteres = substr($url, 0, 4);
    
        if($tresPrimeirosCaracteres === 'www.'){
            $url = substr($url, 4, strlen($url)-1);
        }
    
        return $url;
    }

    /**
     * Método responsável por carregar em memória a lista de TLDs (Top Level Domains) tratados.
     * @return array Retorna um array com os domínios de alto nível, por exemplo: [ 'br' => [ 'com', 'edu' => ['net'] ] ]
     */
    private function processarTlds(){
        $tlds = unserialize(file_get_contents('psl-processado.bin'));
        return $tlds;
    }
    
    /**
     * Deixa os domínios TLDs pré processados em um arquivo .bin em formato de array, para evitar esse tratamento que é relativamente custoso
     * @param string $arquivoTlds Caminho do arquivo já limpo, sem espaços, comentários e outros, do PSL que vai ser pré processado.
     * @return void
     */
    private function preProcessarTlds(){
    
        $arquivoTlds = file_get_contents('psl-limpo.txt');
        $tldsLista = explode(PHP_EOL, $arquivoTlds);
    
        $resultado = [];
        foreach($tldsLista as $tld){
            $partes = explode(".", $tld);
            $partes = array_reverse($partes);
            
            $atual = &$resultado;
            for($i=0; $i < count($partes); $i++){
                $existe = isset($atual[$partes[$i]]);
    
                if(!$existe){
                    $atual[$partes[$i]] = [];
                }
    
                $atual = &$atual[$partes[$i]];
            }
        }
    
        file_put_contents('psl-processado.bin', serialize($resultado));
    }
    
    /**
     * Limpa o arquivo PSL removendo comentários e quebras de linha desnecessárias. Ele pode ser encontrado em: https://publicsuffix.org/
     * @param string $arquivoTlds Caminho do arquivo PSL
     * @return void
     */
    private function limparPsl(){
        $linhas = file('public_suffix_list.dat', FILE_IGNORE_NEW_LINES);
    
        $linhas_filtradas = array_filter($linhas, function($linha) {
            return strpos(ltrim($linha), '//') !== 0 && $linha !== '';
        });
    
        file_put_contents('psl-limpo.txt', implode(PHP_EOL, $linhas_filtradas));
    }

    /**
     * Método responsável por baixar o arquivo PSL mais atual disponível, recomendado ser atualizado uma vez por semana.
     * @return bool
     */
    private function baixarPsl(){
        $arquivoPsl = file_get_contents($this->caminhoNuvemPsl);
        if($arquivoPsl === false) return false;

        file_put_contents('public_suffix_list.dat', $arquivoPsl);
        return true;
    }

    /**
     * Apaga o arquivo de sufixos e o arquivo limpo para poupar armazenamento
     * @return void
     */
    private function limparArquivosTemporarios(){
        if(file_exists('public_suffix_list.dat')){
            unlink('public_suffix_list.dat');
        }

        if(file_exists('./psl-limpo.txt')){
            unlink('./psl-limpo.txt');
        }
    }
}