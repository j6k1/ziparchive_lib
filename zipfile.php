<?php
/**
 * ZipFile
 *
 * @package ZipFile - zip archive
 * @version 0.1.0-alpha
 * @author jinpu <http://will-co21.net>
 * @lisence The LGPL License
 * @copyright Copyright 2012 jinpu. All rights reserved.
 */

/**
 * The ZipFile Class.
 */
class ZipFile
{
	private $filesize;
	private $errmsgs;
	
	private $zipdata;
	private $directory;
	private $entries;
	private $offset;

	public function __construct()
	{
		$this->errmsgs = null;

		$this->zipdata = "";
		$this->directory = "";
		$this->entries = 0;
		$this->offset = 0;
	}
	
	public function add_dir($directory)
	{
		if(!is_array($directory))
		{
			$directory = array($directory);
		}
		
		foreach ($directory as $dir)
		{
			if (preg_match("#.*/$#", $dir) == 0)
			{
				$dir .= '/';
			}

			$this->_add_dir($dir);
		}
	}

	private function _add_dir($dir)
	{
		//unix上で使用する場合、ディレクトリセパレータは必ず/を使うこと。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$dir = self::convToUnixDirectorySeparator($dir, true);
		}
		
		$this->zipdata .=
			"\x50\x4b\x03\x04\x0a\x00\x00\x00\x00\x00\x00\x00\x00\x00"
			.pack('V', 0) // crc32
			.pack('V', 0) // compressed filesize
			.pack('V', 0) // uncompressed filesize
			.pack('v', strlen($dir)) // length of pathname
			.pack('v', 0) // extra field length
			.$dir
			// below is "data descriptor" segment
			.pack('V', 0) // crc32
			.pack('V', 0) // compressed filesize
			.pack('V', 0); // uncompressed filesize

		$this->directory .=
			"\x50\x4b\x01\x02\x00\x00\x0a\x00\x00\x00\x00\x00\x00\x00\x00\x00"
			.pack('V',0) // crc32
			.pack('V',0) // compressed filesize
			.pack('V',0) // uncompressed filesize
			.pack('v', strlen($dir)) // length of pathname
			.pack('v', 0) // extra field length
			.pack('v', 0) // file comment length
			.pack('v', 0) // disk number start
			.pack('v', 0) // internal file attributes
			.pack('V', 16) // external file attributes - 'directory' bit set
			.pack('V', $this->offset) // relative offset of local header
			.$dir;

		$this->offset = strlen($this->zipdata);
		$this->entries++;
	}
	
	public function add_zip_data($filepath, $data = '')
	{
		if (is_array($filepath))
		{
			foreach ($filepath as $path => $data)
			{
				$this->_add_zip_data($path, $data);
			}
		}
		else
		{
			$this->_add_zip_data($filepath, $data);
		}
	}
	
	private function _add_zip_data($filepath, $data)
	{
		//unix上で使用する場合、ディレクトリセパレータは必ず/を使うこと。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$filepath = self::convToUnixDirectorySeparator($filepath, true);
		}
		
		$uncompressed_size = $data['body']['local']['none_compression_size'];
		$crc32  = $data['body']['local']['crc32'];

		$gzdata = $data['body']['data'];
		$compressed_size = $data['body']['local']['compression_size'];

		$this->zipdata .=
			"\x50\x4b\x03\x04\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00"
			.pack('V', $crc32)
			.pack('V', $compressed_size)
			.pack('V', $uncompressed_size)
			.pack('v', strlen($filepath)) // length of filename
			.pack('v', 0) // extra field length
			.$filepath
			.$gzdata; // "file data" segment

		$this->directory .=
			"\x50\x4b\x01\x02\x00\x00\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00"
			.pack('V', $crc32)
			.pack('V', $compressed_size)
			.pack('V', $uncompressed_size)
			.pack('v', strlen($filepath)) // length of filename
			.pack('v', 0) // extra field length
			.pack('v', 0) // file comment length
			.pack('v', 0) // disk number start
			.pack('v', 0) // internal file attributes
			.pack('V', 32) // external file attributes - 'archive' bit set
			.pack('V', $this->offset) // relative offset of local header
			.$filepath;

		$this->offset = strlen($this->zipdata);
		$this->entries++;
	}
	
	public function add_data($filepath, $data = '')
	{
		if (is_array($filepath))
		{
			foreach ($filepath as $path => $data)
			{
				$this->_add_data($path, $data);
			}
		}
		else
		{
			$this->_add_data($filepath, $data);
		}
	}

	private function _add_data($filepath, $data)
	{
		//unix上で使用する場合、ディレクトリセパレータは必ず/を使うこと。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$filepath = self::convToUnixDirectorySeparator($filepath, true);
		}
		
		$uncompressed_size = strlen($data);
		$crc32  = crc32($data);

		$gzdata = gzdeflate($data);
		$compressed_size = strlen($gzdata);

		$this->zipdata .=
			"\x50\x4b\x03\x04\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00"
			.pack('V', $crc32)
			.pack('V', $compressed_size)
			.pack('V', $uncompressed_size)
			.pack('v', strlen($filepath)) // length of filename
			.pack('v', 0) // extra field length
			.$filepath
			.$gzdata; // "file data" segment

		$this->directory .=
			"\x50\x4b\x01\x02\x00\x00\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00"
			.pack('V', $crc32)
			.pack('V', $compressed_size)
			.pack('V', $uncompressed_size)
			.pack('v', strlen($filepath)) // length of filename
			.pack('v', 0) // extra field length
			.pack('v', 0) // file comment length
			.pack('v', 0) // disk number start
			.pack('v', 0) // internal file attributes
			.pack('V', 32) // external file attributes - 'archive' bit set
			.pack('V', $this->offset) // relative offset of local header
			.$filepath;

		$this->offset = strlen($this->zipdata);
		$this->entries++;
	}
	
	public function read_file($path)
	{
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$path = self::convToWinDirectorySeparator($path);
		}
		
		if (!file_exists($path))
		{
			throw new ZipFile_Exception("{$path}が見つかりませんでした。");
		}

		if (($data = file_get_contents($path)) !== false)
		{
			//unix上で使用する場合、ディレクトリセパレータは必ず/を使うこと。
			if(DIRECTORY_SEPARATOR == '\\')
			{
				$name = self::convToUnixDirectorySeparator($path, true);
			}
			else
			{
				$name = $path;
			}
			
			$this->add_data($name, $data);
			return true;
		}
		
		return false;
	}
	
	public function read_dir($path)
	{
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$path = self::convToWinDirectorySeparator($path);
		
			return read_dir_win($path);
		}
		else
		{
			return read_dir_unix($path);
		}
	}

	private function read_dir_win($path)
	{	
		if(preg_match('#.*\x5c$#', $path) == 0)
		{
			$path .= "\x5c";
		}

		if ($fp = @opendir($path))
		{
			while (($file = readdir($fp)) !== false)
			{
				if (@is_dir($path.$file) && ($file != '.') && ($file != '..')) 
				{					
					$this->read_dir_win($path.$file."\x5c");
				}
				elseif (($file != '.') && ($file != '..'))
				{
					if (($data = file_get_contents($path.$file)) !== false)
					{
						$this->add_data(self::convToUnixDirectorySeparator($path, true) . $file, $data);
					}
				}
			}
			return true;
		}
		
		throw new ZipFile_Exception("ディレクトリ{$path}がオープンできませんでした。");
	}

	private function read_dir_unix($path)
	{	
		if(preg_match('#.*/$#', $path) == 0)
		{
			$path .= "/";
		}
		
		if ($fp = @opendir($path))
		{
			while (($file = readdir($fp)) !== false)
			{
				if (@is_dir($path.$file) && ($file != '.') && ($file != '..')) 
				{					
					$this->read_dir($path.$file."/");
				}
				elseif (($file != '.') && ($file != '..'))
				{
					if (($data = file_get_contents($path.$file)) !== false)
					{
						//unix上で使用する場合、ディレクトリセパレータは必ず/を使うこと。
						$this->add_data($path . $file, $data);
					}
				}
			}
			return true;
		}
		
		return false;
	}

	public function get_zip()
	{
		// Is there any data to return?
		if ($this->entries == 0)
		{
			throw new ZipFile_Exception("zipファイルのエントリ数が0です。");
		}

		$zip_data = $this->zipdata;
		$zip_data .= $this->directory."\x50\x4b\x05\x06\x00\x00\x00\x00";
		$zip_data .= pack('v', $this->entries); // total # of entries "on this disk"
		$zip_data .= pack('v', $this->entries); // total # of entries overall
		$zip_data .= pack('V', strlen($this->directory)); // size of central dir
		$zip_data .= pack('V', strlen($this->zipdata)); // offset to start of central dir
		$zip_data .= "\x00\x00"; // .zip file comment length

		return $zip_data;
	}

	public function archive($filepath)
	{
		if(($fp = @fopen($filepath, "wb")) == false)
		{
			throw new ZipFile_Exception("{$filepath}を書き込みモードで開けませんでした。");
		}

		flock($fp, LOCK_EX);	
		fwrite($fp, $this->get_zip());
		flock($fp, LOCK_UN);
		fclose($fp);

		return true;	
	}

	public function download($filename = 'backup.zip')
	{
		if (preg_match("#.*\.zip$#", $filename) == 0)
		{
			$filename .= '.zip';
		}

		$zip_content =& $this->get_zip();

		$this->force_download($filename, $zip_content);
	}

	private function force_download($filename = '', $data = '')
	{
		if ($filename == '' || $data == '')
		{
			throw new ZipFile_Exception("ファイル名もしくはデータが空です。");
		}

		if(preg_match('#\.zip$#', $filename))
		{
			$mime = 'application/x-zip';
		}
		else
		{
			$mime = 'application/octet-stream';
		}
		
		// Generate the server headers
		if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE"))
		{
			header('Content-Type: "'.$mime.'"');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header("Content-Transfer-Encoding: binary");
			header('Pragma: public');
			header("Content-Length: ".strlen($data));
		}
		else
		{
			header('Content-Type: "'.$mime.'"');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header("Content-Transfer-Encoding: binary");
			header('Expires: 0');
			header('Pragma: no-cache');
			header("Content-Length: ".strlen($data));
		}
	
		exit($data);
	}
	
	public function clear_data()
	{
		$this->zipdata = '';
		$this->directory = '';
		$this->entries = 0;
		$this->offset = 0;
	}

	public static function extractToHeaderAndEntry($filepath)
	{
		//windows上で実行している場合、パスをwindows形式に変換
		//※unix上で実行した時はunix形式への変換などは行わない。
		//これはunix上ではバックスラッシュをファイル名などに使えるため、
		//パスの変換が正常に行えない可能性があるため。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$filepath = self::convToWinDirectorySeparator($filepath);
		}
		
		if(!file_exists($filepath))
		{
			throw new ZipFile_Exception("ファイル{$filepath}が見つかりませんでした。");
		}
		
		$filesize = filesize($filepath);
		
		if($filesize < 98)
		{
			throw new ZipFile_Exception("zipファイルのフォーマットが不正です。");
		}
		
		if(($fp = @fopen($filepath, "rb")) == false)
		{
			throw new ZipFile_Exception("zipファイルがオープンできませんでした。");
		}
				
		$zipfile = new ZipFile();
		$zipfile->filesize = $filesize;
		
		$headers = $zipfile->readHeaders($fp);
		$data = array("body" => array(), "tail" => null);
		
		foreach($headers["local"] as $i => $header)
		{
			$data["body"][$i] = array();
			$data["body"][$i]["local"] = $header["header"];
			fseek($fp, $header["data_entry"]);
			if($data["body"][$i]["local"]["compression_size"] > 0)
			{
				$data["body"][$i]["data"] = fread($fp,$data["body"][$i]["local"]["compression_size"]);
			}
			else
			{
				$data["body"][$i]["data"] = "";
			}
			
			$data["body"][$i]["directory"] = $headers["central"][$i]["header"];
		}
		
		$data["tail"] = $headers["endcentral"]["header"];
		
		return $data;
	}
	
	public static function preExtract($filepath)
	{
		return self::preExtractFromLocalFile($filepath);
	}

	public static function preExtractFromLocalFile($filepath)
	{
		$data = self::extractToHeaderAndEntry($filepath);
		
		$result = new ZipFile_On_Memory($data);
		
		return $result;
	}
	
	public static function extract($filepath, $outputpath)
	{
		$result = self::extractFromLocalFile($filepath, $outputpath);
		
		return $result;
	}
	
	public static function extractFromLocalFile($filepath, $outputpath)
	{
		//windows上で実行している場合、パスをwindows形式に変換
		//※unix上で実行した時はunix形式への変換などは行わない。
		//これはunix上ではバックスラッシュをファイル名などに使えるため、
		//パスの変換が正常に行えない可能性があるため。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$filepath = self::convToWinDirectorySeparator($filepath);
			$outputpath = self::convToWinDirectorySeparator($outputpath);
		}
		
		if(!file_exists($filepath))
		{
			throw new ZipFile_Exception("ファイル{$filepath}が見つかりませんでした。");
		}
		
		$filesize = filesize($filepath);
		
		if($filesize < 98)
		{
			throw new ZipFile_Exception("zipファイルのフォーマットが不正です。");
		}
		
		if(($fp = @fopen($filepath, "rb")) == false)
		{
			throw new ZipFile_Exception("zipファイルがオープンできませんでした。");
		}
				
		if($outputpath == ("." . DIRECTORY_SEPARATOR))
		{
			$outputpath = "";
		}
		else if(substr($outputpath, 0, 
			1 + strlen(DIRECTORY_SEPARATOR)) == ("." . DIRECTORY_SEPARATOR))
		{
			$outputpath = substr($outputpath, 1 + strlen(DIRECTORY_SEPARATOR));
		}
		
		if($outputpath == "")
		{
			$extractpath = "";
		}
		else if( (substr($outputpath, 
			     -(strlen(DIRECTORY_SEPARATOR))) == DIRECTORY_SEPARATOR) )
		{
			$extractpath = substr($outputpath, 0, 
				strlen($outputpath) - strlen(DIRECTORY_SEPARATOR));
		}
		else
		{
			$extractpath = $outputpath;
		}

		//windows上で実行している場合、パスをwindowsからunix形式に変換する。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$extractpath = self::convToUnixDirectorySeparator($extractpath, true);
		}
		
		$zipfile = new ZipFile();
		$zipfile->filesize = $filesize;
		
		$headers = $zipfile->readHeaders($fp);
		
		rewind($fp);
		
		$locals = $headers["local"];
		$centrals = $headers["central"];
		$usecentral = true;
		
		if(count($locals) != count($centrals))
		{
			$zipfile->addErrMessage("ローカルファイルヘッダとセントラルディレクトリヘッダの数が一致しません。データが壊れている可能性があります。");
			$usecentral = false;
		}
		
		foreach($locals as $i => $local)
		{
			if(($usecentral) && 
				($local["offset"] != $centrals[$i]["header"]["offset"]))
			{
				$zipfile->addErrMessage("ローカルファイルヘッダの実際のオフセットとセントラルディレクトリファイルヘッダに記録されたオフセットが違います。データが壊れている可能性があります。");
			}
			
			$fileheader = $local["header"];
			
			if( (($usecentral) && 
				 ($centrals[$i]["header"]["external"] & 0x00000010) == 0x10) ||
				 (substr($fileheader["filename"], -1) == "/") )
			{
				$dirname = $extractpath . "/" . $fileheader["filename"];
				
				if(file_exists($dirname) && is_file($dirname))
				{
					$zipfile->addErrMessage("ディレクトリ{$dirname}と同名のファイルが既に存在します。ディレクトリは作成されません。");
				}
				else if(self::mkDirRecursive($dirname) == false)
				{
					$zipfile->addErrMessage("ディレクトリ{$dirname}の作成中にエラーが発生しました。再帰的なディレクトリの作成に失敗しています。");
				}
			}
			else
			{
				$filename = $extractpath . "/" . $fileheader["filename"];
				
				//windows上で実行している場合、パスをwindows形式に変換
				//※なお、解凍しようとしているファイル名やディレクトリ名の第2バイトに
				//'\'が含まれる場合、正常に解凍されない。
				if(DIRECTORY_SEPARATOR == '\\')
				{
					$filename = self::convToWinDirectorySeparator($filename);
				}
				
				$dirname = self::getDirName($filename);
				
				if(!file_exists($dirname))
				{
					if(self::mkDirRecursive($dirname) == false)
					{
						$zipfile->addErrMessage("ディレクトリ{$dirname}の作成中にエラーが発生しました。再帰的なディレクトリの作成に失敗しています。");
					}
				}
				
				fseek($fp, $local["data_entry"]);
				
				if(isset($data))
				{
					unset($data);
				}
				
				$data = fread($fp, $fileheader["compression_size"]);
				$writesize = $fileheader["none_compression_size"];
				
				if($fileheader['compression'] != 8)
				{
					$zipfile->addErrMessage("ファイル{$filename}の圧縮メソッドは本ライブラリで未サポートです。処理をスキップします。");
					continue;
				}
				
				$data = gzinflate($data);
				
				if(crc32($data) != $fileheader["crc32"])
				{
					$zipfile->addErrMessage("ファイル{$filename}のcrcが一致しません。正常に解凍されない可能性があります。");
				}
				
				if(strlen($data) != $writesize)
				{
					$zipfile->addErrMessage("ファイル{$filename}のヘッダ上の解凍後サイズと実際の解凍データのサイズが一致しません。正常に解凍されない可能性があります。");
				}
		
				$last_modified = self::convToUnixTimeStamp(
					$fileheader["lastupdate_date"], $fileheader["lastupdate_time"]);	
				
				if(file_exists($filename))
				{
					if(is_dir($filename))
					{
						$zipfile->addErrMessage("ファイル{$filename}と同名のディレクトリが既に存在します。ファイルは解凍されません。");
						continue;
					}
					else if(filemtime($filename) < $last_modified)
					{
						$zipfile->addErrMessage("ファイル{$filename}は既に存在します。タイムスタンプが新しいので、上書きします。");
					}
					else
					{
						$zipfile->addErrMessage("ファイル{$filename}は既に存在します。タイムスタンプが新しくないので、ファイルは解凍されません。");
						continue;
					}
				}
				
				if(file_exists($filename) && (!is_writeable($filename)))
				{
					$zipfile->addErrMessage("ファイル{$filename}を上書きできません。");
					continue;
				}
				
				if(($writefp = @fopen($filename, 'wb')) == false) 
				{
					$zipfile->addErrMessage("ファイル{$filename}を書き込みモードで開けませんでした。");
					continue;
				}
				
				if((@fwrite($writefp, $data, $writesize)) === false)
				{
					$zipfile->addErrMessage("ファイル{$filename}の解凍データ書き込み時にエラーが発生しました。正常に解凍されなかった可能性があります。");
					continue;
				}
				
				fclose($writefp);
				
				unset($data);
				
				if((@touch($filename, $last_modified)) == false)
				{
					$zipfile->addErrMessage("ファイル{$filename}の最終更新時刻設定でエラーが発生しました。解凍そのものは成功しています。");
				}
			}
		}
		
		
		return (empty($zipfile->errmsgs)) ? true : new ZipFile_Error($zipfile->errmsgs);
	}
	
	private function readHeaders(&$fp)
	{
		$localheaders = $this->readLocalHeaderList($fp);
		$centralheaders = $this->readCentralDirHeaderList($fp);
		$endcentraldirrec =  $this->readEndCentralDir($fp);
		
		return array("local" => $localheaders, 
			"central" => $centralheaders,
			"endcentral" => $endcentraldirrec);
	}
	
	private function readLocalHeaderList(&$fp)
	{
		$p = 0;
		
		$headres = array();
		$data_entries = array();
		
		for($i=0 ;; $i++)
		{
			$data = fread($fp, 4);
			fseek($fp, $p);

			if($data == "\x50\x4b\x03\x04")
			{
				if(($this->filesize - $p) < 66)
				{
					$this->addErrMessage("セントラルディレクトリヘッダを見つける前にファイルの終端に達しました。データが壊れている可能性があります。");
					break;
				}
				
				$localheader = $this->readLocalHeader($fp);
				$p = ftell($fp);
				
				if($localheader === false)
				{
					break;
				}
				
				$p += $localheader['header']['compression_size'];
				fseek($fp, $p);
				
				$headres[$i] = $localheader;
			}
			else if($data == "\x50\x4b\x01\x02")
			{
				break;
			}
			else
			{
				$this->addErrMessage("ローカルファイルヘッダ一覧の読み込み中に不正なデータを見つけました。データが壊れている可能性があります。");
				break;
			}
		}
		
		return $headres;
	}
	
	private function readCentralDirHeaderList(&$fp)
	{
		$p = ftell($fp);

		$headres = array();
		$data_entries = array();
		
		for($i=0 ;; $i++)
		{
			$data = fread($fp, 4);
			fseek($fp, $p);
			
			if($data == "\x50\x4b\x01\x02")
			{
				fseek($fp, $p);
				
				if(($this->filesize - $p) < 20)
				{
					$this->addErrMessage("セントラルディレクトリの終端レコードを見つける前にファイルの終端に達しました。データが壊れている可能性があります。");
					break;
				}
				
				$centralheader = $this->readCentralDirHeader($fp);
				$p = ftell($fp);
				
				if($centralheader === false)
				{
					break;
				}
				
				$headres[$i] = $centralheader;
			}
			else if($data == "\x50\x4b\x05\x06")
			{
				break;
			}
			else
			{
				$this->addErrMessage("セントラルディレクトリヘッダ一覧の読み込み中に不正なデータを見つけました。データが壊れている可能性があります。");
				break;
			}
		}
		
		return $headres;
	}
	
	private function readLocalHeader(&$fp)
	{
		$p = ftell($fp);
		$headeroffset = $p;
		
		if(($this->filesize - $p) < 96)
		{
			$this->addErrMessage("ローカルヘッダの開始位置として指定された値が不正です。");
			return false;
		}
		
		$data = fread($fp, 4);
		
		if($data != "\x50\x4b\x03\x04")
		{
			$this->addErrMessage("ローカルヘッダの開始位置として指定された値が不正です。");
			return false;
		}
		
		$version = unpack('vvalue', fread($fp, 2));
		$bitflag = unpack('vvalue', fread($fp, 2));
		$compression = unpack('vvalue', fread($fp, 2));
		$lastupdate_time = unpack('vvalue', fread($fp, 2));
		$lastupdate_date = unpack('vvalue', fread($fp, 2));
		$crc32 = unpack('Vvalue', fread($fp, 4));
		$compression_size = unpack('Vvalue', fread($fp, 4));
		$none_compression_size = unpack('Vvalue', fread($fp, 4));
		$filename_length = unpack('vvalue', fread($fp, 2));
		$extra_length = unpack('vvalue', fread($fp, 2));
		
		$version = $version['value'];
		$bitflag = $bitflag['value'];
		$compression = $compression['value'];
		$lastupdate_time = $lastupdate_time['value'];
		$lastupdate_date = $lastupdate_date['value'];
		$crc32 = $crc32['value'];
		$compression_size = $compression_size['value'];
		$none_compression_size = $none_compression_size['value'];
		$filename_length = $filename_length['value'];
		$extra_length = $extra_length['value'];

		if($filename_length > 0)
		{
			$filename = fread($fp, $filename_length);
		}
		else
		{
			$filename = "";
		}
		
		if($extra_length > 0)
		{
			$extra_field = fread($fp, $extra_length);
		}
		else
		{
			$extra_field = "";
		}

		$data_entry = ftell($fp);

		$header['version'] = $version;
		$header['bitflag'] = $bitflag;
		$header['compression'] = $compression;
		$header['lastupdate_time'] = $lastupdate_time;
		$header['lastupdate_date'] = $lastupdate_date;
		$header['crc32'] = $crc32;
		$header['compression_size'] = $compression_size;
		$header['none_compression_size'] = $none_compression_size;
		$header['filename_length'] = $filename_length;
		$header['extra_length'] = $extra_length;
		$header['filename'] = $filename;
		$header['extra_field'] = $extra_field;
		
		return array("header" => $header, 
			"data_entry" => $data_entry,
			"offset" => $headeroffset);
	}
	
	private function readCentralDirHeader(&$fp)
	{
		$p = ftell($fp);
		
		if(($this->filesize - $p) < 66)
		{
			$this->addErrMessage("セントラルディレクトリヘッダの開始位置として指定された値が不正です。");
			return false;
		}
		
		$data = fread($fp, 4);
		
		if($data != "\x50\x4b\x01\x02")
		{
			$this->addErrMessage("セントラルディレクトリヘッダの開始位置として指定された値が不正です。");
			return false;
		}
		
		$version = unpack('vvalue', fread($fp, 2));
		$version_extracted =  unpack('vvalue', fread($fp, 2));
		$bitflag = unpack('vvalue', fread($fp, 2));
		$compression = unpack('vvalue', fread($fp, 2));
		$lastupdate_time = unpack('vvalue', fread($fp, 2));
		$lastupdate_date = unpack('vvalue', fread($fp, 2));
		$crc32 = unpack('Vvalue', fread($fp, 4));
		$compression_size = unpack('Vvalue', fread($fp, 4));
		$none_compression_size = unpack('Vvalue', fread($fp, 4));
		$filename_length = unpack('vvalue', fread($fp, 2));
		$extra_length = unpack('vvalue', fread($fp, 2));
		$comment_length = unpack('vvalue', fread($fp, 2));
		$disknumber = unpack('vvalue', fread($fp, 2));
		$internal = unpack('vvalue', fread($fp, 2));
		$external = unpack('Vvalue', fread($fp, 4));
		$offset = unpack('Vvalue', fread($fp, 4));
		
		$version = $version['value'];
		$version_extracted = $version_extracted['value'];
		$bitflag = $bitflag['value'];
		$compression = $compression['value'];
		$lastupdate_time = $lastupdate_time['value'];
		$lastupdate_date = $lastupdate_date['value'];
		$crc32 = $crc32['value'];
		$compression_size = $compression_size['value'];
		$none_compression_size = $none_compression_size['value'];
		$filename_length = $filename_length['value'];
		$extra_length = $extra_length['value'];
		$comment_length = $comment_length['value'];
		$disknumber = $disknumber['value'];
		$internal = $internal['value'];
		$external = $external['value'];
		$offset = $offset['value'];

		if($filename_length > 0)
		{
			$filename = fread($fp, $filename_length);
		}
		else
		{
			$filename = "";
		}
		
		if($extra_length > 0)
		{
			$extra_field = fread($fp, $extra_length);
		}
		else
		{
			$extra_field = "";
		}

		if($comment_length > 0)
		{
			$comment_field = fread($fp, $comment_length);
		}
		else
		{
			$comment_field = "";
		}
		
		$header['version'] = $version;
		$header['version_extracted'] = $version_extracted;
		$header['bitflag'] = $bitflag;
		$header['compression'] = $compression;
		$header['lastupdate_time'] = $lastupdate_time;
		$header['lastupdate_date'] = $lastupdate_date;
		$header['crc32'] = $crc32;
		$header['compression_size'] = $compression_size;
		$header['none_compression_size'] = $none_compression_size;
		$header['filename_length'] = $filename_length;
		$header['extra_length'] = $extra_length;
		$header['comment_length'] = $comment_length;
		$header['disknumber'] = $disknumber;
		$header['internal'] = $internal;
		$header['external'] = $external;
		$header['offset'] = $offset;
		$header['filename'] = $filename;
		$header['extra_field'] = $extra_field;
		$header['comment_field'] = $comment_field;

		return array("header" => $header, "data_entry" => ftell($fp));
	}

	private function readEndCentralDir(&$fp)
	{
		$p = ftell($fp);
		
		if(($this->filesize - $p) < 20)
		{
			$this->addErrMessage("セントラルディレクトリ終端レコードの開始位置として指定された値が不正です。");
			return false;
		}

		$data = fread($fp, 4);
		
		if($data != "\x50\x4b\x05\x06")
		{
			$this->addErrMessage("セントラルディレクトリ終端レコードの開始位置として指定された値が不正です。");
		}
		
		$disk = unpack('vvalue', fread($fp, 2));
		$disk_start = unpack('vvalue', fread($fp, 2));
		$disk_entries = unpack('vvalue', fread($fp, 2));
		$entries = unpack('vvalue', fread($fp, 2));
		$size = unpack('Vvalue', fread($fp, 4));
		$offset = unpack('Vvalue', fread($fp, 4));
		$comment_length = unpack('vvalue', fread($fp, 2));

		$disk = $disk['value'];
		$disk_start = $disk_start['value'];
		$disk_entries = $disk_entries['value'];
		$entries = $entries['value'];
		$size = $size['value'];
		$offset = $offset['value'];
		$comment_length = $comment_length['value'];

		if($comment_length > 0)
		{
			$comment_field = fread($fp, $comment_length);
		}
		else
		{
			$comment_field = "";
		}
		
		$header['disk'] = $disk;
		$header['disk_start'] = $disk_start;
		$header['disk_entries'] = $disk_entries;
		$header['entries'] = $entries;
		$header['size'] = $size;
		$header['offset'] = $offset;
		$header['comment_length'] = $comment_length;
		$header['comment_field'] = $comment_field;
		
		return array("header" => $header, "data_entry" => ftell($fp));
	}
	
	private static function splitWithWindowsDS($path)
	{
		// \x5c('\')を除く連続するShift-JIS文字の列を全て切り出す
		if(preg_match_all(
			'/(?:[\x00-\x5B\x5D-\x7F\xA1-\xDF]|(?:[\x81-\x9F\xE0-\xFC][\x40-\x7E\x80-\xFC]))+/',
			$path, $match))
		{
			return $match[0];
		}
		else
		{
			return array();
		}
	}
	
	public static function convToUnixDirectorySeparator($path, $sjismode = false)
	{
		if($sjismode)
		{
			$tail = "";
			
			if(substr($path, -1) == "\\")
			{
				$tail = "/";
			}
			
			$path = self::splitWithWindowsDS($path);
			return implode("/", $path) . $tail;
		}
		else
		{
			return preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '#',
				'/', $path);
		}
	}
	
	public static function convToWinDirectorySeparator($path, $sjismode = false)
	{
		return preg_replace('#/#', DIRECTORY_SEPARATOR, $path);
	}
	
	public static function convToUnixTimeStamp($date, $time)
	{
		$hour = ($time & 0xF800) >> 11;
		$minute = ($time & 0x07E0) >> 5;
		$second = ($time & 0x001F) *2;
		
		$year = (($date & 0xFE00) >> 9) + 1980;
		$month = ($date & 0x01E0) >> 5;
		$day = ($date & 0x001F);
		
		return mktime($hour, $minute, $second, $month, $day, $year);
	}
	
	public static function getBaseName($filepath)
	{
		//引数として渡すパスはスクリプトを実行しているファイルシステムの
		//形式のみとする。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$filepath = self::splitWithWindowsDS($filepath);
		}
		else
		{
			$filepath = explode(DIRECTORY_SEPARATOR, $filepath);
		}
		
		return $filepath[count($filepath) - 1];
	}
	
	public static function getDirName($filepath)
	{
		if(substr($filepath, 
			-(strlen(DIRECTORY_SEPARATOR))) == DIRECTORY_SEPARATOR)
		{
			return substr($filepath, 0, 
				strlen($filepath) - strlen(DIRECTORY_SEPARATOR));
		}
		
		//引数として渡すパスはスクリプトを実行しているファイルシステムの
		//形式のみとする。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$filepath = self::splitWithWindowsDS($filepath);
		}
		else
		{
			$filepath = explode(DIRECTORY_SEPARATOR, $filepath);
		}
		
		array_pop($filepath);
		
		return implode(DIRECTORY_SEPARATOR, $filepath);
	}
	
	public static function truncateExtension($filename)
	{	
		return preg_replace('/\.[^\.]*$/', '', $filename);
	}
	
	public static function mkDirRecursive($path)
	{
		//ディレクトリは必ず"/"で区切って渡すこと。
		if($path == "")
		{
			return true;
		}
		
		if(substr($path, -1) == "/")
		{
			$path = substr($path, 0, strlen($path) - 1);
		}
		
		//スクリプトを実行しているOSがwindows系である場合、
		//パスを一旦windows形式に変換してチェック。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$checkpath = self::convToWinDirectorySeparator($path);
		}
		else
		{
			$checkpath = $path;
		}
		
		if(file_exists($checkpath))
		{
			if(is_dir($checkpath))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		
		$pathlist = explode("/", $path);
		
		if(is_array($pathlist) && (count($pathlist) > 1))
		{	
			$last = array_pop($pathlist);
			$ret = self::mkDirRecursive(
				implode("/", $pathlist));
			$pathlist[] = $last;
			
			if($ret != true)
			{
				return $ret;
			}
			
			//ファイルシステムのディレクトリ区切り記号でパスを連結してmkdir
			return @mkdir(implode(DIRECTORY_SEPARATOR, $pathlist));
		}
		
		//ここでmkdirに渡されるパスにはディレクトリ区切り記号は含まれない。
		return @mkdir($path);
	}
	
	private function addErrMessage($errmessage)
	{
		if(!isset($this->errmsgs))
		{
			$this->errmsgs = array();
		}
		
		$this->errmsgs[] = $errmessage;
		
		return true;
	}
}
class ZipFile_Exception extends Exception
{

}
class ZipFile_Error
{
	private $errmsgs;
	
	public function __construct($errmsgs)
	{
		$this->errmsgs = $errmsgs;
	}
	
	public static function IsError($instance)
	{
		return ($instance instanceof ZipFile_Error);
	}

	public function dispErrMessages()
	{
		if((!isset($this->errmsgs)) || (!is_array($this->errmsgs)))
		{
			return true;
		}
		
		foreach($this->errmsgs as $message)
		{
			echo "{$message}\n";
		}
		
		return true;
	}
	
	public function getErrMessages()
	{
		return $this->errmsgs;
	}

	public function getErrMessagesHtml($class = null, $style = null)
	{
		if((!isset($this->errmsgs)) || (!is_array($this->errmsgs)))
		{
			return "";
		}
		
		$result = "";
		$attr = "";
		
		$attr .= (!empty($class)) ? " class=\"{$class}\"" : "";
		$attr .= (!empty($style)) ? " style=\"{$style}\"" : "";		
		
		foreach($this->errmsgs as $message)
		{
			$result .= "<div{$attr}>{$message}</div>\n";
		}
		
		return $result;
	}
}
class ZipFile_On_Memory {
	private $data;
	private $footer;
	private $errmsgs;
	
	public function __construct($data)
	{
		$this->errmsgs = null;
		$this->data = array();
		
		$body = $data["body"];
		
		foreach($body as $rec)
		{
			if( (($rec["directory"]["external"] & 0x00000010) == 0x10) ||
				(substr($rec["local"]["filename"], -1) == "/") )
			{
				continue;
			}
			
			$filename = $rec["local"]["filename"];
			
			if($rec["local"]["compression"] != 0x08)
			{
				$this->addErrMessage("ファイル{$filename}の圧縮メソッドは本ライブラリで未サポートです。処理をスキップします。");
				continue;
			}
			
			$this->data[$filename] = array(
				"body" => $rec["data"], 
				"crc" => $rec["local"]["crc32"],
				"size" => $rec["local"]["none_compression_size"],
				"lastupdate_date" => $rec["local"]["lastupdate_date"],
				"lastupdate_time" => $rec["local"]["lastupdate_time"]);
		}
		
		$this->footer = $data["tail"];
	}
	
	public function extractToMemory($filepath)
	{
		$data = $this->data[$filepath];
		
		$result = gzinflate($data["body"]);
		
		if(crc32($result) != $data["crc"])
		{
			$this->addErrMessage("ファイル{$filepath}のcrcが一致しません。正常に解凍されない可能性があります。");
		}
		
		if(strlen($result) != $data["size"])
		{
			$this->addErrMessage("ファイル{$filepath}のヘッダ上の解凍後サイズと実際の解凍データのサイズが一致しません。正常に解凍されない可能性があります。");
		}
		
		return $result;
	}
	
	private function extractSpecificFileToFile($extractpath, $filename)
	{
		$rec = $this->data[$filename];
		
		$filename = $extractpath . "/" . $filename;
		
		//windows上で実行している場合、パスをwindows形式に変換
		//※なお、解凍しようとしているファイル名やディレクトリ名の第2バイトに
		//'\'が含まれる場合、正常に解凍されない。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$filename = ZipFile::convToWinDirectorySeparator($filename);
		}
		
		$dirname = ZipFile::getDirName($filename);
		
		if(!file_exists($dirname))
		{
			if(ZipFile::mkDirRecursive($dirname) == false)
			{
				$this->addErrMessage("ディレクトリ{$dirname}の作成中にエラーが発生しました。再帰的なディレクトリの作成に失敗しています。");
			}
		}
		
		$writesize = $rec["size"];
		
		$data = gzinflate($rec["body"]);
		
		if(crc32($data) != $rec["crc"])
		{
			$this->addErrMessage("ファイル{$filename}のcrcが一致しません。正常に解凍されない可能性があります。");
		}
		
		if(strlen($data) != $writesize)
		{
			$this->addErrMessage("ファイル{$filename}のヘッダ上の解凍後サイズと実際の解凍データのサイズが一致しません。正常に解凍されない可能性があります。");
		}

		$last_modified = ZipFile::convToUnixTimeStamp(
			$rec["lastupdate_date"], $rec["lastupdate_time"]);	
		
		if(file_exists($filename))
		{
			if(is_dir($filename))
			{
				$this->addErrMessage("ファイル{$filename}と同名のディレクトリが既に存在します。ファイルは解凍されません。");
				return;
			}
			else if(filemtime($filename) < $last_modified)
			{
				$this->addErrMessage("ファイル{$filename}は既に存在します。タイムスタンプが新しいので、上書きします。");
			}
			else
			{
				$this->addErrMessage("ファイル{$filename}は既に存在します。タイムスタンプが新しくないので、ファイルは解凍されません。");
				return;
			}
		}
		
		if(file_exists($filename) && (!is_writeable($filename)))
		{
			$this->addErrMessage("ファイル{$filename}を上書きできません。");
			return;
		}
		
		if(($writefp = @fopen($filename, 'wb')) == false) 
		{
			$this->addErrMessage("ファイル{$filename}を書き込みモードで開けませんでした。");
			return;
		}
		
		if((@fwrite($writefp, $data, $writesize)) === false)
		{
			$this->addErrMessage("ファイル{$filename}の解凍データ書き込み時にエラーが発生しました。正常に解凍されなかった可能性があります。");
			return;
		}
		
		fclose($writefp);
		
		unset($data);
		
		if((@touch($filename, $last_modified)) == false)
		{
			$this->addErrMessage("ファイル{$filename}の最終更新時刻設定でエラーが発生しました。解凍そのものは成功しています。");
		}
	}
	
	public function extractToFile($outputpath, $filepath = null)
	{
		//windows上で実行している場合、パスをwindows形式に変換
		//※unix上で実行した時はunix形式への変換などは行わない。
		//これはunix上ではバックスラッシュをファイル名などに使えるため、
		//パスの変換が正常に行えない可能性があるため。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$outputpath = ZipFile::convToWinDirectorySeparator($outputpath);
		}
		
		if($outputpath == ("." . DIRECTORY_SEPARATOR))
		{
			$outputpath = "";
		}
		else if(substr($outputpath, 0, 
			1 + strlen(DIRECTORY_SEPARATOR)) == ("." . DIRECTORY_SEPARATOR))
		{
			$outputpath = substr($outputpath, 1 + strlen(DIRECTORY_SEPARATOR));
		}
		
		if($outputpath == "")
		{
			$extractpath = "";
		}
		else if( (substr($outputpath, 
			     -(strlen(DIRECTORY_SEPARATOR))) == DIRECTORY_SEPARATOR) )
		{
			$extractpath = substr($outputpath, 0, 
				strlen($outputpath) - strlen(DIRECTORY_SEPARATOR));
		}
		else
		{
			$extractpath = $outputpath;
		}

		//windows上で実行している場合、パスをwindowsからunix形式に変換する。
		if(DIRECTORY_SEPARATOR == '\\')
		{
			$extractpath = ZipFile::convToUnixDirectorySeparator($extractpath, true);
		}
		
		if(isset($filepath))
		{
			$this->$this->extractSpecificFileToFile($extractpath, $filepath);
		}
		else
		{
			foreach($this->data as $filename => $rec)
			{
				$this->extractSpecificFileToFile($extractpath, $filename);
			}
		}
		
		return (empty($this->errmsgs)) ? true : new ZipFile_Error($this->errmsgs);
	}
	
	public function isError()
	{
		return ($this->errmsgs !== null);
	}
	
	public function getError()
	{
		return (empty($this->errmsgs)) ? null : new ZipFile_Error($this->errmsgs);
	}
	
	private function addErrMessage($errmessage)
	{
		if(!isset($this->errmsgs))
		{
			$this->errmsgs = array();
		}
		
		$this->errmsgs[] = $errmessage;
		
		return true;
	}
}
?>