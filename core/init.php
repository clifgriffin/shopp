<?php
/**
 * init.php
 * Holds the initial datasets for location based information
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 4 April, 2008
 * @package Shopp
 **/

/**
 * Index of global region names */
function get_global_regions () {
	$regions = array();
	$regions[0] = "North America";
	$regions[1] = "Central America";
	$regions[2] = "South America";
	$regions[3] = "Europe";
	$regions[4] = "Middle East";
	$regions[5] = "Africa";
	$regions[6] = "Asia";
	$regions[7] = "Oceania";
	return $regions;
}

/**
 * Country data table
 * 20 KB in the database, load only when absolutely necessary and unset() asap */
function get_countries () {
	$countries = array();
	$countries['CA'] = array('name'=>'Canada','currency'=>array('code'=>'CAD','format'=>'$#,###.##'),'units'=>'metric','region'=>0); 
	$countries['US'] = array('name'=>'USA','currency'=>array('code'=>'USD','format'=>'$#,###.##'),'units'=>'imperial','region'=>0); 
	$countries['UK'] = array('name'=>'United Kingdom','currency'=>array('code'=>'GBP','format'=>'£#,###.##'),'units'=>'imperial','region'=>3); 
	$countries['AR'] = array('name'=>'Argentina','currency'=>array('code'=>'ARS','format'=>'$#.###,##'),'units'=>'metric','region'=>7); 
	$countries['AU'] = array('name'=>'Australia','currency'=>array('code'=>'AUD','format'=>'$# ###.##'),'units'=>'metric','region'=>7); 
	$countries['AT'] = array('name'=>'Austria','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['BS'] = array('name'=>'Bahamas','currency'=>array('code'=>'BSD','format'=>'$#,###.##'),'units'=>'metric','region'=>0); 
	$countries['BE'] = array('name'=>'Belgium','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['BR'] = array('name'=>'Brazil','currency'=>array('code'=>'BRL','format'=>'R$ #.###,##'),'units'=>'metric','region'=>2); 
	$countries['CL'] = array('name'=>'Chile','currency'=>array('code'=>'CLP','format'=>'$#.###'),'units'=>'metric','region'=>2); 
	$countries['CN'] = array('name'=>'China','currency'=>array('code'=>'CNY','format'=>'¥#,###.##'),'units'=>'metric','region'=>6); 
	$countries['CO'] = array('name'=>'Colombia','currency'=>array('code'=>'COP','format'=>'$#.###,##'),'units'=>'metric','region'=>2); 
	$countries['CR'] = array('name'=>'Costa Rica','currency'=>array('code'=>'CRC','format'=>'¢ #.###,##'),'units'=>'metric','region'=>1); 
	$countries['HR'] = array('name'=>'Croatia','currency'=>array('code'=>'HRK','format'=>'#.###,## kn'),'units'=>'metric','region'=>3); 
	$countries['CY'] = array('name'=>'Cyprus','currency'=>array('code'=>'CYP','format'=>'£#.###,##'),'units'=>'metric','region'=>3); 
	$countries['CZ'] = array('name'=>'Czech Republic','currency'=>array('code'=>'CZK','format'=>'#.###,## Kc'),'units'=>'metric','region'=>3); 
	$countries['DK'] = array('name'=>'Denmark','currency'=>array('code'=>'DKK','format'=>'kr.#.###,##'),'units'=>'metric','region'=>3); 
	$countries['EC'] = array('name'=>'Ecuador','currency'=>array('code'=>'ESC','format'=>'$#,###.##'),'units'=>'metric','region'=>2); 
	$countries['EE'] = array('name'=>'Estonia','currency'=>array('code'=>'EEK','format'=>'# ###,## EEK'),'units'=>'metric','region'=>3); 
	$countries['FI'] = array('name'=>'Finland','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['FR'] = array('name'=>'France','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['DE'] = array('name'=>'Germany','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['GR'] = array('name'=>'Greece','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['GP'] = array('name'=>'Guadeloupe','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['HK'] = array('name'=>'Hong Kong','currency'=>array('code'=>'HKD','format'=>'HK$#,###.##'),'units'=>'metric','region'=>6); 
	$countries['HU'] = array('name'=>'Hungary','currency'=>array('code'=>'HUF','format'=>'#.### Ft'),'units'=>'metric','region'=>3); 
	$countries['IS'] = array('name'=>'Iceland','currency'=>array('code'=>'ISK','format'=>'#.### kr'),'units'=>'metric','region'=>3); 
	$countries['IN'] = array('name'=>'India','currency'=>array('code'=>'INR','format'=>'Rs. #,##,###.##'),'units'=>'metric','region'=>6); 
	$countries['ID'] = array('name'=>'Indonesia','currency'=>array('code'=>'IDR','format'=>'Rp. #.###,##'),'units'=>'metric','region'=>7); 
	$countries['IE'] = array('name'=>'Ireland','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['IL'] = array('name'=>'Israel','currency'=>array('code'=>'ILS','format'=>'#,###.## NIS'),'units'=>'metric','region'=>4); 
	$countries['IT'] = array('name'=>'Italy','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['JM'] = array('name'=>'Jamaica','currency'=>array('code'=>'JMD','format'=>'$#,###.##'),'units'=>'metric','region'=>0); 
	$countries['JP'] = array('name'=>'Japan','currency'=>array('code'=>'JPY','format'=>'¥#,###'),'units'=>'metric','region'=>6); 
	$countries['LV'] = array('name'=>'Latvia','currency'=>array('code'=>'LVL','format'=>'Ls #,###.##'),'units'=>'metric','region'=>3); 
	$countries['LT'] = array('name'=>'Lithuania','currency'=>array('code'=>'LTL','format'=>'# ###,## Lt'),'units'=>'metric','region'=>3); 
	$countries['LU'] = array('name'=>'Luxembourg','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['MY'] = array('name'=>'Malaysia','currency'=>array('code'=>'MYR','format'=>'RM#,###.##'),'units'=>'metric','region'=>6); 
	$countries['MT'] = array('name'=>'Malta','currency'=>array('code'=>'MTL','format'=>'Lm#,###.##'),'units'=>'metric','region'=>3); 
	$countries['MX'] = array('name'=>'Mexico','currency'=>array('code'=>'MXN','format'=>'$ #,###.##'),'units'=>'metric','region'=>0); 
	$countries['NL'] = array('name'=>'Netherlands','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['NZ'] = array('name'=>'New Zealand','currency'=>array('code'=>'NZD','format'=>'$#,###.##'),'units'=>'metric','region'=>7); 
	$countries['NO'] = array('name'=>'Norway','currency'=>array('code'=>'NOK','format'=>'kr #.###,##'),'units'=>'metric','region'=>3); 
	$countries['PH'] = array('name'=>'Philippines','currency'=>array('code'=>'PHP','format'=>'PHP#,###.##'),'units'=>'metric','region'=>6); 
	$countries['PL'] = array('name'=>'Poland','currency'=>array('code'=>'PLZ','format'=>'#.###,## zł'),'units'=>'metric','region'=>3); 
	$countries['PT'] = array('name'=>'Portugal','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['PR'] = array('name'=>'Puerto Rico','currency'=>array('code'=>'USD','format'=>'$#,###.##'),'units'=>'imperial','region'=>0); 
	$countries['RO'] = array('name'=>'Romania','currency'=>array('code'=>'ROL','format'=>'#.###,## lei'),'units'=>'metric','region'=>3); 
	$countries['RU'] = array('name'=>'Russia','currency'=>array('code'=>'RUB','format'=>'RUB#.###,##'),'units'=>'metric','region'=>6); 
	$countries['SG'] = array('name'=>'Singapore','currency'=>array('code'=>'SGD','format'=>'$#,###.##'),'units'=>'metric','region'=>6); 
	$countries['SK'] = array('name'=>'Slovakia','currency'=>array('code'=>'SKK','format'=>'# ###,## Sk'),'units'=>'metric','region'=>3); 
	$countries['SI'] = array('name'=>'Slovenia','currency'=>array('code'=>'SIT','format'=>'#.###,## SIT'),'units'=>'metric','region'=>3); 
	$countries['ZA'] = array('name'=>'South Africa','currency'=>array('code'=>'ZAR','format'=>'R # ###.##'),'units'=>'metric','region'=>5); 
	$countries['KR'] = array('name'=>'South Korea','currency'=>array('code'=>'KRW','format'=>'\\#,###'),'units'=>'metric','region'=>6); 
	$countries['ES'] = array('name'=>'Spain','currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3); 
	$countries['VC'] = array('name'=>'St. Vincent','currency'=>array('code'=>'XCD','format'=>'$#,###.##'),'units'=>'metric','region'=>6); 
	$countries['SE'] = array('name'=>'Sweden','currency'=>array('code'=>'SEK','#.###,## kr'),'units'=>'metric','region'=>3); 
	$countries['CH'] = array('name'=>'Switzerland','currency'=>array('code'=>'CHF','format'=>'SFr. #\'###.##'),'units'=>'metric','region'=>3); 
	$countries['SY'] = array('name'=>'Syria','currency'=>array('code'=>'SYP','format'=>'#,###.## SYP'),'units'=>'metric','region'=>4); 
	$countries['TW'] = array('name'=>'Taiwan','currency'=>array('code'=>'TWD','format'=>'$#,###.##'),'units'=>'metric','region'=>6); 
	$countries['TH'] = array('name'=>'Thailand','currency'=>array('code'=>'THB','format'=>'#,###.## Bt'),'units'=>'metric','region'=>6); 
	$countries['TT'] = array('name'=>'Trinidad and Tobago','currency'=>array('code'=>'TTD','format'=>'TT$#,###.##'),'units'=>'metric','region'=>0); 
	$countries['TR'] = array('name'=>'Turkey','currency'=>array('code'=>'TRL','format'=>'#,###.## TL'),'units'=>'metric','region'=>4); 
	$countries['AE'] = array('name'=>'United Arab Emirates','currency'=>array('code'=>'AED','format'=>'Dhs. #,###.##'),'units'=>'metric','region'=>4); 
	$countries['UY'] = array('name'=>'Uruguay','currency'=>array('code'=>'UYP','format'=>'$#,###.##'),'units'=>'metric','region'=>2); 
	$countries['VE'] = array('name'=>'Venezuela','currency'=>array('code'=>'VUB','format'=>'Bs. #,###.##'),'units'=>'metric','region'=>2); 
	return $countries;
}

/**
 * State/Province/Territory zone names
 * 2 KB in the database */
function get_country_zones() {
	$zones = array();
	$zones['AU'] = array();
	$zones['AU']['NSW'] = 'New South Wales';
	$zones['AU']['NT'] = 'Northern Territory';
	$zones['AU']['QLD'] = 'Queensland';
	$zones['AU']['TAS'] = 'Tasmania';
	$zones['AU']['VIC'] = 'Victoria';
	$zones['AU']['WA'] = 'Western Australia';

	$zones['CA'] = array();
	$zones['CA']['AB'] = 'Alberta';
	$zones['CA']['BC'] = 'British Columbia';
	$zones['CA']['MB'] = 'Manitoba';
	$zones['CA']['NB'] = 'New Brunswick';
	$zones['CA']['NF'] = 'Newfoundland';
	$zones['CA']['NT'] = 'Northwest Territories';
	$zones['CA']['NS'] = 'Nova Scotia';
	$zones['CA']['NU'] = 'Nunavut';
	$zones['CA']['ON'] = 'Ontario';
	$zones['CA']['PE'] = 'Prince Edward Island';
	$zones['CA']['PQ'] = 'Quebec';
	$zones['CA']['SK'] = 'Saskatchewan';
	$zones['CA']['YT'] = 'Yukon Territory';

	$zones['US'] = array();
	$zones['US']['AL'] = 'Alabama';
	$zones['US']['AK'] = 'Alaska ';
	$zones['US']['AZ'] = 'Arizona';
	$zones['US']['AR'] = 'Arkansas';
	$zones['US']['CA'] = 'California ';
	$zones['US']['CO'] = 'Colorado';
	$zones['US']['CT'] = 'Connecticut';
	$zones['US']['DE'] = 'Delaware';
	$zones['US']['DC'] = 'District Of Columbia ';
	$zones['US']['FL'] = 'Florida';
	$zones['US']['GA'] = 'Georgia ';
	$zones['US']['HI'] = 'Hawaii';
	$zones['US']['ID'] = 'Idaho';
	$zones['US']['IL'] = 'Illinois';
	$zones['US']['IN'] = 'Indiana';
	$zones['US']['IA'] = 'Iowa';
	$zones['US']['KS'] = 'Kansas';
	$zones['US']['KY'] = 'Kentucky';
	$zones['US']['LA'] = 'Louisiana';
	$zones['US']['ME'] = 'Maine';
	$zones['US']['MD'] = 'Maryland';
	$zones['US']['MA'] = 'Massachusetts';
	$zones['US']['MI'] = 'Michigan';
	$zones['US']['MN'] = 'Minnesota';
	$zones['US']['MS'] = 'Mississippi';
	$zones['US']['MO'] = 'Missouri';
	$zones['US']['MT'] = 'Montana';
	$zones['US']['NE'] = 'Nebraska';
	$zones['US']['NV'] = 'Nevada';
	$zones['US']['NH'] = 'New Hampshire';
	$zones['US']['NJ'] = 'New Jersey';
	$zones['US']['NM'] = 'New Mexico';
	$zones['US']['NY'] = 'New York';
	$zones['US']['NC'] = 'North Carolina';
	$zones['US']['ND'] = 'North Dakota';
	$zones['US']['OH'] = 'Ohio';
	$zones['US']['OK'] = 'Oklahoma';
	$zones['US']['OR'] = 'Oregon';
	$zones['US']['PA'] = 'Pennsylvania';
	$zones['US']['RI'] = 'Rhode Island';
	$zones['US']['SC'] = 'South Carolina';
	$zones['US']['SD'] = 'South Dakota';
	$zones['US']['TN'] = 'Tennessee';
	$zones['US']['TX'] = 'Texas';
	$zones['US']['UT'] = 'Utah';
	$zones['US']['VT'] = 'Vermont';
	$zones['US']['VA'] = 'Virginia';
	$zones['US']['WA'] = 'Washington';
	$zones['US']['WV'] = 'West Virginia';
	$zones['US']['WI'] = 'Wisconsin';
	$zones['US']['WY'] = 'Wyoming';
	return $zones;
}

/**
 * Domestic areas for US and Canada mapped by postcode
 * 3 KB in the database */
function get_country_areas () {
	$areas = array();
	$areas['CA'] = array();
	$areas['CA']['Northern Canada'] = array('YT'=>array('Y'),'NT'=>array('X'),'NU'=>array('X'));
	$areas['CA']['Western Canada'] = array('BC'=>array('V'),'AB'=>array('T'),'SK'=>array('S'),'MB'=>array('R'));
	$areas['CA']['Eastern Canada'] = array('OT'=>array('K','L','M','N','P'),'PQ'=>array('G','H','J'),'NB'=>array('E'),'PE'=>array('C'),'NS'=>array('B'),'NF'=>array('A'));

	$areas['US'] = array();
	$areas['US']['Northeast US'] = array('MA'=>array('01000','02799'),'RI'=>array('02800','02999'),'NH'=>array('03000','03899'),'ME'=>array('04000','04999'),'VT'=>array('05000','05999'),'CT'=>array('06800','06999'),'NJ'=>array('07000','08999'),'NY'=>array('10000','14999'),'PA'=>array('15000','16999'));
	$areas['US']['Midwest US'] = array('OH'=>array('43000','45899'),'IN'=>array('46000','47999'),'MI'=>array('48000','49799'),'IA'=>array('50000','52899'),'WI'=>array('53000','54899'),'MN'=>array('55000','56799'),'SD'=>array('57000','57799'),'ND'=>array('58000','58899'),'IL'=>array('60000','62999'),'MO'=>array('63000','65899'),'KS'=>array('66000','64799'),'NE'=>array('68000','69399'));
	$areas['US']['South US'] =array('DE'=>array('19700','19999'),'DC'=>array('20001','20599'),'MD'=>array('20600','21999'),'VA'=>array('22000','24699'),'WV'=>array('25000','26899'),'NC'=>array('27000','28999'),'SC'=>array('29000','29999'),'GA'=>array('30000','31999'),'FL'=>array('32100','34999'),'AL'=>array('35000','36999'),'TN'=>array('37000','38899'),'MS'=>array('38600','39599'),'KY'=>array('40000','42799'),'LA'=>array('70000','71499'),'AR'=>array('71600','72999'),'OK'=>array('73000','74999'),'TX'=>array('75000','79999'));
	$areas['US']['West US'] =array('MT'=>array('59000','59999'),'CO'=>array('80000','81699'),'WY'=>array('82000','83199'),'ID'=>array('83200','83899'),'UT'=>array('84000','84799'),'AZ'=>array('85000','86599'),'NM'=>array('87000','88499'),'NV'=>array('89000','89899'),'CA'=>array('90000','96199'),'HI'=>array('96700','96899'),'OR'=>array('97000','97999'),'WA'=>array('98000','99499'),'AK'=>array('99500','99999'));
	return $areas;	
}

?>