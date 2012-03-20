<?php
/**
 * ZipFile
 *
 * @package ZipFile - zip archive
 * @version 0.0.1-alpha
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
	
	public function __construct()
	{
		$this->errmsgs = null;
	}
	
	public static function extract($filepath, $outputpath)
	{
		$zipfile = self::extractFromLocalFile($filepath, $outputpath);
		
		$zipfile->dispErrmessages();
		
		return;
	}
	
	public static function extractFromLocalFile($filepath, $outputpath)
	{
		$filepath = self::convDirectorySeparator($filepath);
		$outputpath = self::convDirectorySeparator($outputpath);

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

		$extractpath = self::convToUnixDirectorySeparator($extractpath);

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
				$filename = self::convDirectorySeparator($filename);
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
		
				$last_modified = $zipfile->convToUnixTimeStamp(
					$fileheader["lastupdate_date"], $fileheader["lastupdate_time"]);	
				
				if(file_exists($filename))
				{
					if(filemtime($filename) < $last_modified)
					{
						$zipfile->addErrMessage("ファイル{$filename}は既に存在します。タイムスタンプが新しいので、上書きします。");
					}
					else if(!is_dir($filename))
					{
						$zipfile->addErrMessage("ファイル{$filename}は既に存在します。タイムスタンプが新しくないので、ファイルは解凍されません。");
						continue;
					}
					else if(is_dir($filename))
					{
						$zipfile->addErrMessage("ファイル{$filename}と同名のディレクトリが既に存在します。ファイルは解凍されません。");
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
		
		
		return $zipfile;
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
				ddbreak;
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
		
		return array("header" => $header, "data_entry" => ftell($fp));
	}
	
	private static function convToUnixDirectorySeparator($path)
	{
		return preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '#',
			'/', $path);
	}
	
	private static function convDirectorySeparator($path)
	{
		return preg_replace('#/#', DIRECTORY_SEPARATOR, $path);
	}
	
	private function convToUnixTimeStamp($date, $time)
	{
		$hour = ($time & 0xF800) >> 11;
		$minute = ($time & 0x07E0) >> 5;
		$second = ($time & 0x001F) *2;
		
		$year = (($date & 0xFE00) >> 9) + 1980;
		$month = ($date & 0x01E0) >> 5;
		$day = ($date & 0x001F);
		
		return mktime($hour, $minute, $second, $month, $day, $year);
	}
	
	private static function getBaseName($filepath)
	{
		$filepath = explode(DIRECTORY_SEPARATOR, $filepath);
		return $filepath[count($filepath) - 1];
	}
	
	private static function getDirName($filepath)
	{
		if(substr($filepath, 
			-(strlen(DIRECTORY_SEPARATOR))) == DIRECTORY_SEPARATOR)
		{
			return substr($filepath, 0, 
				strlen($filepath) - strlen(DIRECTORY_SEPARATOR));
		}
		
		$filepath = explode(DIRECTORY_SEPARATOR, $filepath);
		array_pop($filepath);
		
		return implode(DIRECTORY_SEPARATOR, $filepath);
	}
	
	private static function truncateExtension($filename)
	{	
		return preg_replace('/\.[^\.]*$/', '', $filename);
	}
	
	private static function mkDirRecursive($path)
	{
		if($path == "")
		{
			return true;
		}
		
		if(substr($path, -1) == "/")
		{
			$path = substr($path, 0, strlen($path) - 1);
		}
		
		if(file_exists(self::convDirectorySeparator($path)))
		{
			if(is_dir(self::convDirectorySeparator($path)))
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
			
			return @mkdir(implode(DIRECTORY_SEPARATOR, $pathlist));
		}
		
		return @mkdir($path);
	}
	
	public function dispErrmessages()
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
?>