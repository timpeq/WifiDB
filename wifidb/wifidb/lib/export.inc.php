<?php
/*
Export.inc.php, holds the WiFiDB exporting functions.
Copyright (C) 2012 Phil Ferland

This program is free software; you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation; either
version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

ou should have received a copy of the GNU General Public License along with this program;
if not, write to the

   Free Software Foundation, Inc.,
   59 Temple Place, Suite 330,
   Boston, MA 02111-1307 USA
*/
class export extends dbcore
{
	public function __construct($config, $createKMLObj, $createGeoJSONObj, $convertObj, $ZipObj){
		parent::__construct($config);

		$this->convert = $convertObj;
		$this->createKML = $createKMLObj;
		$this->createGeoJSON = $createGeoJSONObj;
		$this->Zip = $ZipObj;
		$this->daemon_folder_stats = array();
		$this->named = 0;
		$this->month_names  = array(
			1=>'January',
			2=>'February',
			3=>'March',
			4=>'April',
			5=>'May',
			6=>'June',
			7=>'July',
			8=>'August',
			9=>'September',
			10=>'October',
			11=>'November',
			12=>'December',
		);
		$this->ver_array['export']  =   array(
			"last_edit"			 =>  "2015-10-11",
			"ExportDaemonKMZ"		  =>  "1.0",
			"ExportSingleAP"		=>  "1.0",
			"ExportCurrentAP"	=>  "1.0",
			"ExportApSignal3d"	=>  "1.0",	
			"UserAll"		=>  "3.0",			
			"UserList"		=>  "3.0",
			"UserAllGeoJSON"		=>  "1.0",
			"UserListGeoJSON"		=>  "1.0",
			"FindBox"	=>  "1.0",
			"distance"	=>  "2.0",
			"get_point"	=>  "2.0",
			"CreateBoundariesKML"	=>  "1.0",
			"ExportGPXAll"	=>  "1.0",			
			"GenerateDaemonKMLData" =>  "1.1",
			"HistoryKMLLink"		=>  "1.0",
			"GenerateUpdateKML"	 =>  "1.0",
		);
	}


	/*
	 * Export to Google KML File
	 */
	public function ExportDaemonKMZ($kmz_filepath, $type = "full", $only_new = 0, $new_icons = 0)
	{
		$this->verbosed("Compiling Data for Export.");

		if($type == "full")
		{
			#Create Queries
			$user_query = "SELECT DISTINCT(user) FROM `files` WHERE completed = 1 ORDER BY `user` ASC";
			$user_list_query = "SELECT `id`, `user`, `title`, `date` FROM `files` WHERE `user` LIKE ? And completed = 1";
		}
		elseif($type == "daily")
		{
			#Get the date of the latest import
			$sql = "SELECT `date` FROM `files` WHERE completed = 1 ORDER BY `date` DESC LIMIT 1";
			$date_query = $this->sql->conn->query($sql);
			$date_fetch = $date_query->fetch(2);
			$datestamp = $date_fetch['date'];
			$datestamp_split = explode(" ", $datestamp);
			$latest_date = $datestamp_split[0];
			$latest_date = (empty($latest_date)) ? date($this->date_format) : $latest_date;
			
			#Create Queries
			$date_search = $latest_date."%";
			$user_query = "SELECT DISTINCT(user) FROM `files` WHERE completed = 1 And `date` LIKE '$date_search' ORDER BY `user` ASC";
			$user_list_query = "SELECT `id`, `user`, `title`, `date` FROM `files` WHERE completed = 1 And `user` LIKE ? AND `date` LIKE '$date_search'";
		}	
		
		$ZipC = clone $this->Zip;
		
		#Get list of users and go through them
		$results="";
		$lists = 0;
		$prep_user = $this->sql->conn->query($user_query);
		$fetch_user = $prep_user->fetchAll();
		$prep_user_list = $this->sql->conn->prepare($user_list_query);
		foreach($fetch_user as $user)
		{
			#Get users lists and go through them
			$user_results = "";
			$user_files = 0;
			$username = $user['user'];
			$prep_user_list->bindParam(1, $username, PDO::PARAM_STR);
			$prep_user_list->execute();
			$fetch_imports = $prep_user_list->fetchAll();
			foreach($fetch_imports as $import)
			{
				$id = $import['id'];
				$title = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $id.'_'.$import['title']);
				$ListKML = $this->UserList($id, $this->named, $only_new, $new_icons);
				if($ListKML['data'] !== "")
				{
					$final_box = $this->FindBox($ListKML['box']);
					$KML_region = $this->createKML->PlotRegionBox($final_box, uniqid());
					$list_results = $KML_region.$ListKML['data'];

					#Create List KML Structure
					$list_results = $this->createKML->createFolder($title, $list_results, 0);
					$list_results = $this->createKML->createKMLstructure($title, $list_results);

					#Add list kml into final kmz
					if($this->named){$list_kml_name = $username."_".$title."_label.kml";}else{$list_kml_name = $username."_".$title.".kml";}
					$ZipC->addFile($list_results, 'files/'.$list_kml_name);
					unset($list_results);
					
					#Create Network Link to this kml for the final doc.kml
					$Netlink_region = $this->createKML->PlotRegionBox($final_box, uniqid());
					$user_results .= $this->createKML->createNetworkLink('files/'.$list_kml_name, $title.' ( List ID:'.$id.')' , 1, 0, "onChange", 86400, 0, $Netlink_region);

					#Increment variables (duh)
					++$user_files;
					++$lists;
				}
			}
			#If this user had results, create a folder with their data
			if($user_results){$results .= $this->createKML->createFolder($username.' ('.$user_files.' Files)', $user_results, 0);}
			unset($user_results);
		}
		#Create the final KMZ
		if($results == ""){$results = $this->createKML->createFolder("No Exports with GPS", $results, 0);}else{$results = $this->createKML->createFolder("All Exports", $results, 1);}
		#$regions_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/boundaries.kml', "Regions to save precious CPU cycles.", 1, 0, "once", 60);
		#$results .= $this->createKML->createFolder("WifiDB Newest AP", $regions_link, 1, 1);
		
		$results = $this->createKML->createFolder($type." Database Export", $results, 1);
		$results = $this->createKML->createKMLstructure("WiFiDB ".$type." Database Export", $results);
		
		$this->verbosed("Writing the ".$type." KMZ File. ($lists Lists) : ".$kmz_filepath);
		$ZipC->addFile($results, 'doc.kml');
		$ZipC->setZipFile($kmz_filepath);
		$ZipC->getZipFile();
		if (file_exists($kmz_filepath)) 
		{
			$this->verbosed("KMZ created at ".$kmz_filepath);
			chmod($kmz_filepath, 0664);
			###
			$link = $this->daemon_out.basename($kmz_filepath);
			$this->verbosed('Creating symlink from "'.$kmz_filepath.'" to "'.$link.'"');
			unlink($link);
			symlink($kmz_filepath, $link);
			chmod($link, 0664);
			Return true;
		}
		else
		{
			$this->verbosed("KMZ file does not exist :/ ");
			Return false;
		}
	}
	
	public function ExportSingleAp($id, $named=0, $new_icons=0)
	{
		$KML_data = "";
		$export_ssid="";
		
		$sql = "SELECT wap.AP_ID, wap.BSSID, wap.SSID, wap.CHAN, wap.AUTH, wap.ENCR, wap.SECTYPE, wap.RADTYPE, wap.NETTYPE, wap.BTX, wap.OTX,\n"
			. "whFA.Hist_Date As FA,\n"
			. "whLA.Hist_Date As LA,\n"
			. "wGPS.Lat As Lat,\n"
			. "wGPS.Lon As Lon,\n"
			. "wGPS.Alt As Alt,\n"
			. "wf.user As user\n"
			. "FROM `wifi_ap` AS wap\n"
			. "LEFT JOIN wifi_hist AS whFA ON whFA.Hist_ID = wap.FirstHist_ID\n"
			. "LEFT JOIN wifi_hist AS whLA ON whLA.Hist_ID = wap.LastHist_ID\n"
			. "LEFT JOIN wifi_gps AS wGPS ON wGPS.GPS_ID = wap.HighGps_ID\n"
			. "LEFT JOIN files AS wf ON whFA.File_ID = wf.id\n"
			. "WHERE `wap`.`AP_ID` = ?";
		$result = $this->sql->conn->prepare($sql);
		$result->bindParam(1, $id, PDO::PARAM_INT);
		$result->execute();
		$appointer = $result->fetchAll();
		foreach($appointer as $ap)
		{
			$export_ssid=$ap['SSID'];
			$ap_info = array(
			"id" => $ap['AP_ID'],
			"new_ap" => $new_icons,
			"named" => $named,
			"mac" => $ap['BSSID'],
			"ssid" => $ap['SSID'],
			"chan" => $ap['CHAN'],
			"radio" => $ap['RADTYPE'],
			"NT" => $ap['NETTYPE'],
			"sectype" => $ap['SECTYPE'],
			"auth" => $ap['AUTH'],
			"encry" => $ap['ENCR'],
			"BTx" => $ap['BTX'],
			"OTx" => $ap['OTX'],
			"FA" => $ap['FA'],
			"LA" => $ap['LA'],
			"lat" => $this->convert->dm2dd($ap['Lat']),
			"lon" => $this->convert->dm2dd($ap['Lon']),
			"alt" => $ap['Alt'],
			"manuf"=>$this->findManuf($ap['BSSID']),
			"user" => $ap['user']
			);

			$KML_data = $this->createKML->CreateApPlacemark($ap_info);
		}

		if($KML_data == ""){$KML_data = $this->createKML->createFolder("AP has no GPS", $KML_data, 0);}
		
		return array($KML_data, $export_ssid);
	}

	public function ExportCurrentAP($named=0, $new_icons=0)
	{
		$KML_data="";
		$sql = "SELECT `id`, `ssid`, `ap_hash` FROM `wifi_pointers` WHERE `lat` != '0.0000' ORDER BY `id` DESC LIMIT 1";
		$result = $this->sql->conn->query($sql);
		$ap_array = $result->fetch(2);
		if($ap_array['id'])
		{
			$id = (int)$ap_array['id'];
			list($KML_AP_data, $export_ssid) = $this->ExportSingleAp($id, $named, $new_icons);
			$KML_data = $KML_AP_data;
		}
		Return $KML_data;
	}
	
	public function ExportSignal3dSingleListAp($file_id, $ap_id, $visible = 0)
	{
		$KML_data = "";
		#Get Import Name
		$sql = "SELECT `title`, `date` FROM `files` WHERE `id` = ?";
		$prep_title = $this->sql->conn->prepare($sql);
		$prep_title->bindParam(1, $file_id, PDO::PARAM_INT);
		$prep_title->execute();
		$fetch_title = $prep_title->fetch(2);
		$ap_list_title = $fetch_title['title'];
		$ap_list_date = $fetch_title['date'];
		
		#Get AP Signal History for this file	
		$sql = "SELECT\n"
			. "`wifi_hist`.Sig, `wifi_hist`.RSSI, `wifi_hist`.Hist_Date,\n"
			. "`wifi_gps`.Lat, `wifi_gps`.`Lon`, `wifi_gps`.NumOfSats, `wifi_gps`.HorDilPitch, `wifi_gps`.Alt, \n"
			. "`wifi_gps`.Geo, `wifi_gps`.KPH, `wifi_gps`.MPH, `wifi_gps`.TrackAngle, `wifi_gps`.GPS_Date\n"
			. "FROM `wifi_hist`\n"
			. "LEFT JOIN `wifi_gps` ON `wifi_hist`.`GPS_ID` = `wifi_gps`.`GPS_ID`\n"
			. "WHERE `wifi_hist`.`AP_ID` = ? AND `wifi_hist`.`File_ID` = ? AND `wifi_gps`.`Lat` != '0.0000'\n"
			. "ORDER BY `wifi_gps`.`GPS_Date` ASC";
		$ap_query = $this->sql->conn->prepare($sql);
		$ap_query->bindParam(1, $ap_id, PDO::PARAM_INT);
		$ap_query->bindParam(2, $file_id, PDO::PARAM_INT);
		$ap_query->execute();
		$sig_gps_data = $ap_query->fetchAll(2);
		if(count($sig_gps_data) > 0)
		{
			#Plot AP 3D Signal
			$KML_signal = $this->createKML->CreateApSignal3D($sig_gps_data, 1 ,1);
			$KML_data .= $this->createKML->createFolder($file_id.' - '.$ap_list_title.' - '.$ap_list_date, $KML_signal, 0, 0, $visible);
		}
		return $KML_data;
	}
	
	public function UserAll($user)
	{
		$sql = "SELECT wifi_ap.AP_ID\n"
			. "FROM `wifi_ap`\n"
			. "LEFT JOIN `files` ON `files`.`id` = `wifi_ap`.`File_ID`\n"
			. "WHERE `files`.`user` LIKE ? And  `wifi_ap`.`BSSID` != '00:00:00:00:00:00' And `wifi_ap`.`HighGps_ID` IS NOT NULL";
		$result1 = $this->sql->conn->prepare($sql);
		$result1->bindParam(1, $user, PDO::PARAM_STR);
		$result1->execute();
		$KML_data="";
		while($array = $result1->fetch(2))
		{
			list($KML_AP_data, $export_ssid) = $this->ExportSingleAp($array['AP_ID'], $this->named);
			if($KML_AP_data){$KML_data .= $KML_AP_data;}
		}
		return $KML_data;
	}

	public function UserList($file_id, $named=0, $only_new=0, $new_icons=0)
	{
		$sql = "SELECT DISTINCT(`AP_ID`) From `wifi_hist` WHERE `File_ID` = ?";
		$prep_AP_IDS = $this->sql->conn->prepare($sql);
		$prep_AP_IDS->bindParam(1,$file_id, PDO::PARAM_INT);
		$prep_AP_IDS->execute();
		$Import_KML_Data="";
		$box_latlon = array();
		while ( $array = $prep_AP_IDS->fetch(2) )
		{
			$apid = $array['AP_ID'];
			$sql = "SELECT wap.AP_ID, wap.BSSID, wap.SSID, wap.CHAN, wap.AUTH, wap.ENCR, wap.SECTYPE, wap.RADTYPE, wap.NETTYPE, wap.BTX, wap.OTX,\n"
				. "whFA.Hist_Date As FA,\n"
				. "whLA.Hist_Date As LA,\n"
				. "wGPS.Lat As Lat,\n"
				. "wGPS.Lon As Lon,\n"
				. "wGPS.Alt As Alt,\n"
				. "wf.user As user\n"
				. "FROM `wifi_ap` AS wap\n"
				. "LEFT JOIN wifi_hist AS whFA ON whFA.Hist_ID = wap.FirstHist_ID\n"
				. "LEFT JOIN wifi_hist AS whLA ON whLA.Hist_ID = wap.LastHist_ID\n"
				. "LEFT JOIN wifi_gps AS wGPS ON wGPS.GPS_ID = wap.HighGps_ID\n"
				. "LEFT JOIN files AS wf ON whFA.File_ID = wf.id\n"
				. "WHERE `wap`.`AP_ID` = ? And `wap`.`HighGps_ID` IS NOT NULL And `wap`.`BSSID` != '00:00:00:00:00:00'";
			$result = $this->sql->conn->prepare($sql);
			$result->bindParam(1, $apid, PDO::PARAM_INT);
			$result->execute();
			$appointer = $result->fetchAll();
			foreach($appointer as $ap)
			{
				$export_ssid=$ap['SSID'];
				$ap_info = array(
				"id" => $ap['AP_ID'],
				"new_ap" => $new_icons,
				"named" => $named,
				"mac" => $ap['BSSID'],
				"ssid" => $ap['SSID'],
				"chan" => $ap['CHAN'],
				"radio" => $ap['RADTYPE'],
				"NT" => $ap['NETTYPE'],
				"sectype" => $ap['SECTYPE'],
				"auth" => $ap['AUTH'],
				"encry" => $ap['ENCR'],
				"BTx" => $ap['BTX'],
				"OTx" => $ap['OTX'],
				"FA" => $ap['FA'],
				"LA" => $ap['LA'],
				"lat" => $this->convert->dm2dd($ap['Lat']),
				"lon" => $this->convert->dm2dd($ap['Lon']),
				"alt" => $ap['Alt'],
				"manuf"=>$this->findManuf($ap['BSSID']),
				"user" => $ap['user']
				);

				$Import_KML_Data .=$this->createKML->CreateApPlacemark($ap_info);
				
				$latlon_info = array(
				"lat" => $this->convert->dm2dd($ap['Lat']),
				"long" => $this->convert->dm2dd($ap['Lon'])
				);
				$box_latlon[] = $latlon_info;
			}
		}

		$ret_data = array(
		"data" => $Import_KML_Data,
		"box" => $box_latlon,
		);
		
		return $ret_data;
	}

	public function UserAllGeoJSON($user, $from = NULL, $inc = NULL)
	{
		$Import_Map_Data = "";
		$latlon_array = array();
		$apcount = 0;
		
		$sql = "SELECT `wap`.`AP_ID`, `wap`.`BSSID`, `wap`.`SSID`, `wap`.`CHAN`, `wap`.`AUTH`, `wap`.`ENCR`, `wap`.`SECTYPE`, `wap`.`RADTYPE`, `wap`.`NETTYPE`, `wap`.`BTX`, `wap`.`OTX`,\n"
			. "`whFA`.`Hist_Date` As `FA`,\n"
			. "`whLA`.`Hist_Date` As `LA`,\n"
			. "`wGPS`.`Lat` As `Lat`,\n"
			. "`wGPS`.`Lon` As `Lon`,\n"
			. "`wf`.`user` AS `user`\n"
			. "FROM `wifi_ap` AS `wap`\n"
			. "LEFT JOIN `wifi_hist` AS `whFA` ON `whFA`.`Hist_ID` = `wap`.`FirstHist_ID`\n"
			. "LEFT JOIN `wifi_hist` AS `whLA` ON `whLA`.`Hist_ID` = `wap`.`LastHist_ID`\n"
			. "LEFT JOIN `wifi_gps`As `wGPS` ON `wGPS`.`GPS_ID` = `wap`.`HighGps_ID`\n"
			. "LEFT JOIN `files` AS `wf` ON `wf`.`id` = `wap`.`File_ID`\n"
			. "WHERE \n"
			. "    `wap`.`HighGps_ID` IS NOT NULL And\n"
			. "    `wap`.`BSSID` != '00:00:00:00:00:00' And\n"
			. "    `wap`.`AP_ID` IN (\n"
			. "        SELECT DISTINCT (`wifi_hist`.`AP_ID`)\n"
			. "        FROM `wifi_hist`\n"
			. "        WHERE \n"
			. "            `wifi_hist`.`File_ID` IN (\n"
			. "                SELECT DISTINCT (`files`.`id`)\n"
			. "                FROM `files`\n"
			. "                WHERE `files`.`user` LIKE ? And `files`.`completed` = 1 And `files`.`ValidGPS` = 1\n"
			. "            )\n"
			. "    )\n"
			. "ORDER BY `wap`.`ModDate` DESC";
#			. "        FROM `wifi_hist`\n"
#			. "        INNER JOIN `wifi_ap` ON `wifi_ap`.`AP_ID` = `wifi_hist`.`AP_ID`\n"
#			. "        INNER JOIN `files` ON `files`.`id` = `wifi_hist`.`File_ID`\n"
#			. "        WHERE \n"
#			. "        	`wifi_ap`.`HighGps_ID` IS NOT NULL And\n"
#			. "        	`wifi_ap`.`BSSID` != '00:00:00:00:00:00' And\n"
#			. "        	`files`.`completed` = 1 And\n"
#			. "        	`files`.`ValidGPS` = 1 And\n"
#			. "        	`files`.`user` LIKE ?
#			. "    )\n"
#			. "ORDER BY `wap`.`ModDate` DESC";

		if($from !== NULL And $inc !== NULL){$sql .=  " LIMIT ".$from.", ".$inc;}
		
		$prep = $this->sql->conn->prepare($sql);
		$prep->bindParam(1, $user, PDO::PARAM_STR);
		$prep->execute();
		$apcount = $prep->rowCount();
		$appointer = $prep->fetchAll();
		foreach($appointer as $apinfo)
		{
			#Get AP GeoJSON
			$ap_info = array(
			"id" => $apinfo['AP_ID'],
			"new_ap" => $new_icons,
			"named" => $named,
			"mac" => $apinfo['BSSID'],
			"ssid" => $apinfo['SSID'],
			"chan" => $apinfo['CHAN'],
			"radio" => $apinfo['RADTYPE'],
			"NT" => $apinfo['NETTYPE'],
			"sectype" => $apinfo['SECTYPE'],
			"auth" => $apinfo['AUTH'],
			"encry" => $apinfo['ENCR'],
			"BTx" => $apinfo['BTX'],
			"OTx" => $apinfo['OTX'],
			"FA" => $apinfo['FA'],
			"LA" => $apinfo['LA'],
			"lat" => $this->convert->dm2dd($apinfo['Lat']),
			"lon" => $this->convert->dm2dd($apinfo['Lon']),
			"alt" => $apinfo['Alt'],
			"manuf"=>$this->findManuf($apinfo['BSSID']),
			"user" => $apinfo['user']
			);
			if($Import_Map_Data !== ''){$Import_Map_Data .=',';};
			$Import_Map_Data .=$this->createGeoJSON->CreateApFeature($ap_info);
			
			$latlon_info = array(
			"lat" => $this->convert->dm2dd($apinfo['Lat']),
			"long" => $this->convert->dm2dd($apinfo['Lon']),
			);
			$latlon_array[] = $latlon_info;
		}
		$ret_data = array(
			"count" => $apcount,
			"data" => $Import_Map_Data,
			"latlongarray" => $latlon_array,
		);
		
		return $ret_data;
	}

	public function UserListGeoJSON($file_id, $new_icons=0)
	{
		$sql = "SELECT DISTINCT(`AP_ID`) From `wifi_hist` WHERE `File_ID` = ?";
		$prep_AP_IDS = $this->sql->conn->prepare($sql);
		$prep_AP_IDS->bindParam(1,$file_id, PDO::PARAM_INT);
		$prep_AP_IDS->execute();
		$Import_Map_Data="";
		$latlon_array = array();
		while ( $array = $prep_AP_IDS->fetch(2) )
		{
			$apid = $array['AP_ID'];
			$sql = "SELECT wap.AP_ID, wap.BSSID, wap.SSID, wap.CHAN, wap.AUTH, wap.ENCR, wap.SECTYPE, wap.RADTYPE, wap.NETTYPE, wap.BTX, wap.OTX,\n"
				. "whFA.Hist_Date As FA,\n"
				. "whLA.Hist_Date As LA,\n"
				. "wGPS.Lat As Lat,\n"
				. "wGPS.Lon As Lon,\n"
				. "wGPS.Alt As Alt,\n"
				. "wf.user As user\n"
				. "FROM `wifi_ap` AS wap\n"
				. "LEFT JOIN wifi_hist AS whFA ON whFA.Hist_ID = wap.FirstHist_ID\n"
				. "LEFT JOIN wifi_hist AS whLA ON whLA.Hist_ID = wap.LastHist_ID\n"
				. "LEFT JOIN wifi_gps AS wGPS ON wGPS.GPS_ID = wap.HighGps_ID\n"
				. "LEFT JOIN files AS wf ON whFA.File_ID = wf.id\n"
				. "WHERE `wap`.`AP_ID` = ? And `wap`.`HighGps_ID` IS NOT NULL And `wap`.`BSSID` != '00:00:00:00:00:00'";
			$result = $this->sql->conn->prepare($sql);
			$result->bindParam(1, $apid, PDO::PARAM_INT);
			$result->execute();
			$appointer = $result->fetchAll();
			foreach($appointer as $ap)
			{
				#Get AP GeoJSON
				$ap_info = array(
				"id" => $ap['AP_ID'],
				"new_ap" => $new_icons,
				"mac" => $ap['BSSID'],
				"ssid" => $ap['SSID'],
				"chan" => $ap['CHAN'],
				"radio" => $ap['RADTYPE'],
				"NT" => $ap['NETTYPE'],
				"sectype" => $ap['SECTYPE'],
				"auth" => $ap['AUTH'],
				"encry" => $ap['ENCR'],
				"BTx" => $ap['BTX'],
				"OTx" => $ap['OTX'],
				"FA" => $ap['FA'],
				"LA" => $ap['LA'],
				"lat" => $this->convert->dm2dd($ap['Lat']),
				"lon" => $this->convert->dm2dd($ap['Lon']),
				"alt" => $ap['Alt'],
				"manuf"=>$this->findManuf($ap['BSSID']),
				"user" => $ap['user'],
				);
				if($Import_Map_Data !== ''){$Import_Map_Data .=',';};
				$Import_Map_Data .=$this->createGeoJSON->CreateApFeature($ap_info);
				
				$latlon_info = array(
				"lat" => $this->convert->dm2dd($ap['Lat']),
				"long" => $this->convert->dm2dd($ap['Lon']),
				);
				$latlon_array[] = $latlon_info;
			}
		}

		$ret_data = array(
		"data" => $Import_Map_Data,
		"latlongarray" => $latlon_array,
		);
		
		return $ret_data;
	}

	function FindBox($points = array())
	{
		$North = NULL;
		$South = NULL;
		$East = NULL;
		$West = NULL;
		foreach($points as $elements)
		{
			$lat = $elements['lat'];
			$long = $elements['long'];
			
			if($North == NULL)
			{
				$North = $lat;
			}
			if($South == NULL)
			{
				$South = $lat;
			}

			if($East == NULL)
			{
				$East = $long;
			}
			if($West == NULL)
			{
				$West = $long;
			}

			if((float)$North < (float)$lat)
			{
				$North = $lat;
			}
			if((float)$South > (float)$lat)
			{
				$South = $lat;
			}
			if((float)$East < (float)$long)
			{
				$East = $long;
			}
			if((float)$West > (float)$long)
			{
				$West = $long;
			}
		}

		if(($this->distance($North, $East, $South, $East) <= 2) && ($this->distance($North, $East, $North, $West) <= 2))
		{
			$minLodPixels = 8;
			$RegionSize = 2;
			list($center_lat, $unused) = $this->get_midpoint($North, $East, $South, $East);
			list($unused, $center_lon) = $this->get_midpoint($North, $East, $North, $West);
			list($North, $unused) = $this->get_point($center_lat, $center_lon, 0, $RegionSize/2);
			list($South, $unused) = $this->get_point($center_lat, $center_lon, 180, $RegionSize/2);
			list($unused, $West) = $this->get_point($center_lat, $center_lon, 270, $RegionSize/2);
			list($unused, $East) = $this->get_point($center_lat, $center_lon, 90, $RegionSize/2);
		}
		elseif(($this->distance($North, $East, $South, $East) <= 4) && ($this->distance($North, $East, $North, $West) <= 4))
		{
			$minLodPixels = 16;
			$RegionSize = 4;
		}
		elseif(($this->distance($North, $East, $South, $East) <= 8) && ($this->distance($North, $East, $North, $West) <= 8))
		{
			$minLodPixels = 32;
			#$RegionSize = 8;
		}
		elseif(($this->distance($North, $East, $South, $East) <= 16) && ($this->distance($North, $East, $North, $West) <= 16))
		{
			$minLodPixels = 64;
			#$RegionSize = 16;
		}
		elseif(($this->distance($North, $East, $South, $East) <= 32) && ($this->distance($North, $East, $North, $West) <= 32))
		{
			$minLodPixels = 128;
			#$RegionSize = 32;
		}
		elseif(($this->distance($North, $East, $South, $East) <= 64) && ($this->distance($North, $East, $North, $West) <= 64))
		{
			$minLodPixels = 256;
			#$RegionSize = 64;
		}
		elseif(($this->distance($North, $East, $South, $East) <= 128) && ($this->distance($North, $East, $North, $West) <= 128))
		{
			$minLodPixels = 512;
			#$RegionSize = 128;
		}
		elseif(($this->distance($North, $East, $South, $East) <= 256) && ($this->distance($North, $East, $North, $West) <= 256))
		{
			$minLodPixels = 1024;
			#$RegionSize = 256;
		}
		else
		{
			$minLodPixels = 2048;
			#$RegionSize = 512;
		}
		
		$maxLodPixels = -1;
		
		return array( $North, $South, $East, $West, $minLodPixels, $maxLodPixels);
	}

	function distance($lat1, $lon1, $lat2, $lon2)
	{
		$radius = 6371;#radius in kilometers
		$rad1 = deg2rad($lat1);
		$rad2 = deg2rad($lat2);
		$del1 =  deg2rad($lat2-$lat1);
		$del2 =  deg2rad($lon2-$lon1);
		$a = sin($del1/2) * sin($del1/2) + cos($rad1) * cos($rad2) * sin($del2/2) * sin($del2/2);
		$c = 2 * atan2(sqrt($a), sqrt(1-$a));
		$d = $radius * $c;
		
		Return $d;
	}

	function get_point($lat1, $lon1, $bearing, $distance)
	{
		$radius = 6371;#radius in kilometers
		$rlat1 = deg2rad($lat1); 
		$rlon1 = deg2rad($lon1);
		$radial = deg2rad($bearing);
		$lat_rad = asin(sin($rlat1) * cos($distance/$radius) + cos($rlat1) * sin($distance/$radius) * cos($radial));
		$dlon_rad = atan(sin($radial) * sin($distance/$radius) * cos($rlat1)) / (cos($distance/$radius) - sin($rlat1) * sin($lat_rad));
		$lon_rad = fmod(($rlon1 + $dlon_rad + M_PI), 2 * M_PI) - M_PI;
		
		$coord[0] = rad2deg($lat_rad);
		$coord[1] = rad2deg($lon_rad);	
		return $coord;
	}
	
	function get_midpoint($lat1, $lon1, $lat2, $lon2)
	{
		$rlat1 = deg2rad($lat1);
		$rlat2 = deg2rad($lat2);
		$rlon1 = deg2rad($lon1);
		$rlon2 = deg2rad($lon2);
		$Bx = cos($rlat2) * cos($rlon2-$rlon1);
		$By = cos($rlat2) * sin($rlon2-$rlon1);
		$rlat3 = atan2(sin($rlat1) + sin($rlat2),sqrt((cos($rlat1)+$Bx)*(cos($rlat1)+$Bx) + $By*$By));
		$rlon3 = $rlon1 + atan2($By, cos($rlat1) + $Bx);
		
		$coord[0] = rad2deg($rlat3);
		$coord[1] = rad2deg($rlon3);	
		return $coord;
	}

	public function CreateBoundariesKML()
	{
		$boundaries_kml_file = $this->PATH.'out/daemon/boundaries.kml';
		$this->verbosed("Generating World Boundaries KML File : ".$boundaries_kml_file);

		$results = $this->sql->conn->query("SELECT * FROM `boundaries`");
		$fetched = $results->fetchAll(2);
		$KML_data = "";
		foreach($fetched as $boundary)
		{
			$KML_data .= $this->createKML->PlotBoundary($boundary);
		}

		$KMLFolderdata = $this->createKML->createFolder("World Boundaries", $KML_data, 0);
		$this->createKML->createKML($boundaries_kml_file, "World Boundaries", $KMLFolderdata);
		chmod($boundaries_kml_file, 0664);
		return $boundaries_kml_file;
	}

	/*
	 * Export to Garmin GPX File
	 */
	public function ExportGPXAll()
	{
		$this->verbosed("Starting GPX Export of WiFiDB.");
		$sql = "SELECT * FROM `wifi_pointers` WHERE `lat` != '0.0000' AND `mac` != '00:00:00:00:00:00' ORDER by `id` ASC";
		$prep = $this->sql->conn->execute($sql);
		$aparray_all = $prep->fetchAll(2);
		$this->verbosed("Pointers Table Queried.");
		$err = $this->sql->conn->errorCode();
		if($err[0] !== "00000")
		{
			$this->logd("Error fetching from Pointers table to generate GPX All: ".var_export($this->sql->conn->errorInfo(), 1));
			$this->verbosed("Error Fetching data from Pointers Table :(", -1);
			return -1;
		}

		foreach($aparray_all as $aparray)
		{
			$file_data  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\" ?>
<gpx xmlns=\"http://www.topografix.com/GPX/1/1\"
	creator=\"WiFiDB 0.16 Build 2\"
	version=\"1.1\"
	xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
	xsi:schemaLocation=\"http://www.topografix.com/GPX/1/1\">";
			// write file header buffer var

			$type = $aparray['sectype'];
			switch($type)
			{
				case 1:
					$color = "Navaid, Green";
					break;
				case 2:
					$color = "Navaid, Amber";
					break;
				case 3:
					$color = "Navaid, Red";
					break;
				default:
					$color = "Navaid, Green";
					break;
			}
			$date = $aparray["date"];
			$time = $aparray["time"];
			$alt = $aparray['alt'] * 3.28;
			$lat = $this->convert->dm2dd($aparray['lat']);
			$long = $this->convert->dm2dd($aparray['long']);

			$file_data .= "<wpt lat=\"".$lat."\" lon=\"".$long."\">\r\n"
				."<ele>".$alt."</ele>\r\n"
				."<time>".$date."T".$time."Z</time>\r\n"
				."<name>".$aparray['ssid']."</name>\r\n"
				."<cmt>".$aparray['mac']."</cmt>\r\n"
				."<desc>".$aparray['label']."</desc>\r\n"
				."<sym>".$color."</sym>\r\n<extensions>\r\n"
				."<gpxx:WaypointExtension xmlns:gpxx=\"http://www.garmin.com/xmlschemas/GpxExtensions/v3\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.garmin.com/xmlschemas/GpxExtensions/v3 http://www.garmin.com/xmlschemas/GpxExtensions/v3/GpxExtensionsv3.xsd\">\r\n"
				."<gpxx:DisplayMode>SymbolAndName</gpxx:DisplayMode>\r\n<gpxx:Categories>\r\n"
				."<gpxx:Category>Category ".$type."</gpxx:Category>\r\n</gpxx:Categories>\r\n</gpxx:WaypointExtension>\r\n</extensions>\r\n</wpt>\r\n\r\n";
			if($aparray['rssi'])
			{
				$signals = explode("\\", $aparray['signals']);
			}else
			{
				$signals = explode("-",$aparray['sig']);
			}

			$file_data .= "<trk>\r\n<name>GPS Track</name>\r\n<trkseg>\r\n";
			foreach($signals as $signal)
			{
				$sig_exp	= explode(",",$signal);
				$gpsid	  = $sig_exp[0];

				$sql = "SELECT * FROM `wifi_gps` WHERE `id` = ? LIMIT 1";
				$prepgps = $this->sql->conn->prepare($sql);
				$prepgps->bindParam(1, $gpsid, PDO::PARAM_INT);
				$prepgps->execute();
				$gps = $prepgps->fetch(2);

				$alt = $gps['alt'] * 3.28;

				$lat =& $this->convert->dm2dd($gps['lat']);

				$long =& $this->convert->dm2dd($gps['long']);
				$file_data .= "<trkpt lat=\"".$lat."\" lon=\"".$long."\">\r\n"
					."<ele>".$alt."</ele>\r\n"
					."<time>".$date."T".$time."Z</time>\r\n"
					."</trkpt>\r\n";
			}
			$this->verbosed('Plotted AP: '.$aparray['ssid']);
		}

		$file_data .= "</trkseg>\r\n</trk></gpx>";
		$file_ext = "wifidb_".date($this->datetime_format).".gpx";
		$filename = ($this->gpx_out.$file_ext);
		$filewrite = fopen($filename, "w");
		if($filewrite == FALSE)
		{
			$this->logd("Error trying to write the GPX file: $filename");
			$this->verbosed("Error trying to write the GPX file: $filename  :(", -1);
			return -1;
		}
		$fileappend = fopen($filename, "a");
		fwrite($fileappend, $file_data);
		fclose($fileappend);

		#chmod($daily_folder.'/full_db'.$labeled.'.kml', 0750);

		return 1;
	}

	/*
	 * Generate the Daily Daemon KML files
	 */
	public function GenerateDaemonKMLData()
	{
		$date = date($this->date_format);
		$daily_folder = $this->PATH.'out/daemon/'.$date;
		if(!@file_exists($daily_folder))
		{
			$this->verbosed("Need to make a daily export folder...", 1);
			if(!@mkdir($daily_folder))
			{
				$this->verbosed("Error making new daily export folder...", -1);
			}
		}
		
		#Generate Full KML if it doesn't already exist
		$this->named = 0;
		$kmz_filepath = $daily_folder."/full_db.kmz";
		if(!file_exists($kmz_filepath))
		{
			$this->verbosed("Generating Full DB KML");
			$this->ExportDaemonKMZ($kmz_filepath, "full" ,1 ,0);
		}
		
		#Generate Full Labeled KML if it doesn't already exist
		$this->named = 1;
		$kmz_filepath = $daily_folder."/full_db_label.kmz";
		if(!file_exists($kmz_filepath))
		{
			$this->verbosed("Generating Full DB Labeled KML");
			$this->ExportDaemonKMZ($kmz_filepath, "full" ,1 ,0);
		}
		
		#Generate Daily KML
		$this->named = 0;
		$kmz_filepath = $daily_folder."/daily_db.kmz";
		$this->ExportDaemonKMZ($kmz_filepath, "daily" ,0 ,1);
		
		#Generate Daily Labeled KML
		$this->named = 1;
		$kmz_filepath = $daily_folder."/daily_db_label.kmz";
		$this->ExportDaemonKMZ($kmz_filepath, "daily" ,0 ,1);

		#Generate History KML
		if($this->HistoryKMLLink() === -1)
		{
			$this->verbosed("Failed to Create Daemon History KML Links", -1);
		}else
		{
			$this->verbosed("Created Daemon History KML Links");
		}

		#Generate Update KML
		if($this->GenerateUpdateKML() === -1)
		{
			$this->verbosed("Failed to Create Update.kml File", -1);
		}else
		{
			$this->verbosed("Created Update.kml File");
		}
		return 1;
	}

	/*
	 * Create the Archival KML links
	 */
	public function HistoryKMLLink()
	{
		$this->daemon_folder_stats['history'] = array();
		$daemon_export = $this->PATH."out/daemon/";
		$dir = opendir($daemon_export);
		$files = array();
		while ($file = readdir($dir))
		{
			if($file == "." || $file == ".." || $file == ".svn"){continue;}
			if(is_dir($daemon_export.$file))
			{
				$files[] = $file;
			}
		}
		sort($files);
		closedir($dir);

		foreach($files as $entry)
		{
			$matches = array();
			preg_match("/([0-9]{4}\-[0-9]{2}\-[0-9]{2})/", $entry, $matches, PREG_OFFSET_CAPTURE);
			if(@$matches[0])
			{
				$date_exp = explode("-", $entry);
				$year = $date_exp[0]+0;
				$month = $date_exp[1]+0;
				$day = $date_exp[2]+0;
				$month_label = $this->month_names[$month];
				$this->daemon_folder_stats['history'][$year][$month_label][$day] = $entry;
			}

		}
		$generated = array();
		foreach($this->daemon_folder_stats['history'] as $key=>$year)
		{
			$output = $daemon_export.'history/'.$key.'.kmz';
			$current_year = date("Y")+0;
			if(file_exists($output) && $key != $current_year)
			{
				$generated[] = $key.'.kmz';
				continue;
			}
			$kml_data = '<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Folder>
		<name>'.$key.'</name>
		<open>0</open>';

			foreach($year as $key1=>$month)
			{
				$kml_data .= '
		<Folder>
				<name>'.$key1.'</name>
				<open>0</open>';
				foreach($month as $key2=>$day)
				{
					if(file_exists($daemon_export.$day.'/daily_db.kmz'))
					{
						$daily_db_kmz_nl = '
						<NetworkLink>
								<name>Daily KMZ</name>
								<visibility>0</visibility>
								<Link>
										<href>'.$this->URL_PATH.'out/daemon/'.$day.'/daily_db.kmz</href>
								</Link>
						</NetworkLink>';
					}else
					{
						$daily_db_kmz_nl = '';
					}

					if(file_exists($daemon_export.$day.'/daily_db_label.kmz'))
					{
						$daily_db_kmz_label_nl = '
						<NetworkLink>
								<name>Daily Labeled KMZ</name>
								<visibility>0</visibility>
								<Link>
										<href>'.$this->URL_PATH.'out/daemon/'.$day.'/daily_db_label.kmz</href>
								</Link>
						</NetworkLink>';
					}else
					{
						$daily_db_kmz_label_nl = '';
					}

					if(file_exists($daemon_export.$day.'/full_db.kmz'))
					{
						$full_db_kmz_nl = '
						<NetworkLink>
								<name>Full DB KMZ</name>
								<visibility>0</visibility>
								<Link>
										<href>'.$this->URL_PATH.'out/daemon/'.$day.'/full_db.kmz</href>
								</Link>
						</NetworkLink>';
					}else
					{
						$full_db_kmz_nl = '';
					}

					if(file_exists($daemon_export.$day.'/full_db_label.kmz'))
					{
						$full_db_label_kmz_nl = '
						<NetworkLink>
								<name>Full DB Labeled KMZ</name>
								<visibility>0</visibility>
								<Link>
										<href>'.$this->URL_PATH.'out/daemon/'.$day.'/full_db_label.kmz</href>
								</Link>
						</NetworkLink>';
					}else
					{
						$full_db_label_kmz_nl = '';
					}

					$kml_data .= '
				<Folder>
						<name>'.$key2.'</name>
						<open>0</open>'.$daily_db_kmz_nl.
						$daily_db_kmz_label_nl.
						$full_db_kmz_nl.
						$full_db_label_kmz_nl.'
				</Folder>';
				}
				$kml_data .= '</Folder>';
			}
			$kml_data .= '</Folder></kml>';
			
			$this->Zip->addFile($kml_data, 'doc.kml');
			$this->Zip->setZipFile($output);
			$this->Zip->getZipFile();
			
			if (file_exists($output)) 
			{
				$this->verbosed("KMZ created at ".$output);
				chmod($output, 0664);
			}
			else
			{
				$this->verbosed("Failed to Create KMZ file :/ ");
			}		

			$generated[] = $key.'.kmz';
		}

		$kml_data = '<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Folder>
		<name>WiFiDB Archive</name>
		<open>0</open>';
		foreach($generated as $year)
		{
			$year_name = str_replace(".kml", "", $year);
			$kml_data .= '
				<NetworkLink>
						<name>'.$year_name.'</name>
						<visibility>0</visibility>
						<Link>
								<href>'.$this->URL_PATH.'out/daemon/history/'.$year.'</href>
						</Link>
				</NetworkLink>';
		}
		$kml_data .= '
</Folder>
</kml>';
		$output = $daemon_export.'history.kml';
		
		$this->Zip->addFile($kml_data, 'doc.kml');
		$this->Zip->setZipFile($output);
		$this->Zip->getZipFile();
		
		if (file_exists($output)) 
		{
			$this->verbosed("KMZ created at ".$output);
			chmod($output, 0664);
		}
		else
		{
			$this->verbosed("Failed to Create KMZ file :/ ");
		}		
	}

	/*
	 * Generate the updated KML Link
	 */
	public function GenerateUpdateKML()
	{
		$full_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/full_db.kmz', "Full DB Export (No Label)", 0, 0, "onInterval", 3600).
			$this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/full_db_label.kmz', "Full DB Export (Label)", 0, 0, "onInterval", 3600);
		$full_folder = $this->createKML->createFolder("WifiDB Full DB Export", $full_link, 1, 1);

		$daily_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/daily_db.kmz', "Daily DB Export (No Label)", 1, 0, "onInterval", 3600).
			$this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/daily_db_label.kmz', "Daily DB Export (Label)", 0, 0, "onInterval", 3600);
		$daily_folder = $this->createKML->createFolder("WifiDB Daily DB Export", $daily_link, 1, 1);

		$new_AP_link = $this->createKML->createNetworkLink($this->URL_PATH.'api/latest.php?labeled=0',"Newest AP w/ Fly To (No Label)", 0, 1, "onInterval", 60).
			$this->createKML->createNetworkLink($this->URL_PATH.'api/latest.php?labeled=1',"Newest AP w/ Fly To (Labeled)", 0, 1, "onInterval", 60).
			$this->createKML->createNetworkLink($this->URL_PATH.'api/latest.php?labeled=0',"Newest AP (No Label)", 0, 0, "onInterval", 60).
			$this->createKML->createNetworkLink($this->URL_PATH.'api/latest.php?labeled=1',"Newest AP (Labeled)", 1, 0, "onInterval", 60);
		$new_AP_folder = $this->createKML->createFolder("WifiDB Newest AP", $new_AP_link, 1, 1);

		//$archive_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/history.kmz', "Archived History", 0, 0, "onInterval", 86400);
		//$archive_folder = $this->createKML->createFolder("Historical Archives", $archive_link, 1);

		//$KML_data = $full_folder.$daily_folder.$new_AP_folder.$regions_folder;#.$archive_folder;
		$KML_data = $new_AP_folder.$daily_folder.$full_folder;#.$archive_folder;
		$KML_data = $this->createKML->createKMLstructure("Combined KMZ Network Link", $KML_data);
		
		$kmz_filename = $this->daemon_out.'update.kmz';
		$this->verbosed("Writing KMZ : ".$kmz_filename);
		$this->Zip->addFile($KML_data, 'doc.kml');
		$this->Zip->setZipFile($kmz_filename);
		$this->Zip->getZipFile();
		
		if (file_exists($kmz_filename)) 
		{
			$this->verbosed("KMZ created at ".$kmz_filename);
			chmod($kmz_filename, 0664);
		}
		else
		{
			$this->verbosed("Failed create KMZ file :/ ");
		}
		
		return $kmz_filename;
	}
}
