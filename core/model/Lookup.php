<?php
/**
 * Lookup.php
 *
 * Provides reference data tables
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February  3, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage references
 **/

/**
 * Lookup
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class Lookup {

	/**
	 * Provides a lookup table worldwide regions
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of regions
	 **/
	static function regions () {
		$_ = array();
		$_[0] = __("North America","Shopp");
		$_[1] = __("Central America","Shopp");
		$_[2] = __("South America","Shopp");
		$_[3] = __("Europe","Shopp");
		$_[4] = __("Middle East","Shopp");
		$_[5] = __("Africa","Shopp");
		$_[6] = __("Asia","Shopp");
		$_[7] = __("Oceania","Shopp");
		return apply_filters('shopp_regions',$_);
	}

	/**
	 * Finds the translated region name for a specific region index
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return string The translated region name
	 **/
	static function region ($id) {
		$r = Lookup::regions();
		return $r[$id];
	}

	/**
	 * Returns a lookup table of supported country defaults
	 *
	 * The information in the following table has been derived from
	 * the ISO standard documents including ISO-3166 for 2-letter country
	 * codes and ISO-4217 for currency codes
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array
	 **/
	static function countries () {
		$_ = array();
		$_['CA'] = array('name'=>__('Canada','Shopp'),'currency'=>array('code'=>'CAD','format'=>'$#,###.##'),'units'=>'metric','region'=>0);
		$_['US'] = array('name'=>__('USA','Shopp'),'currency'=>array('code'=>'USD','format'=>'$#,###.##'),'units'=>'imperial','region'=>0);
		// Specialized "country" for US Armed Forces
	  $_['USAF'] = array('name'=>__('US Armed Forces','Shopp'),'currency'=>array('code'=>'USD','format'=>'$#,###.##'),'units'=>'imperial','region'=>0);
		$_['GB'] = array('name'=>__('United Kingdom','Shopp'),'currency'=>array('code'=>'GBP','format'=>'£#,###.##'),'units'=>'metric','region'=>3);
		$_['DZ'] = array('name'=>__('Algeria','Shopp'),'currency'=>array('code'=>'DZD','format'=>'#,###.## .د.ج'),'units'=>'metric','region'=>5);
		$_['AR'] = array('name'=>__('Argentina','Shopp'),'currency'=>array('code'=>'ARS','format'=>'$#.###,##'),'units'=>'metric','region'=>2);
		$_['AW'] = array('name'=>__('Aruba','Shopp'),'currency'=>array('code'=>'AWG','format'=>'ƒ#,###.##'),'units'=>'metric','region'=>2);
		$_['AU'] = array('name'=>__('Australia','Shopp'),'currency'=>array('code'=>'AUD','format'=>'$# ###.##'),'units'=>'metric','region'=>7);
		$_['AT'] = array('name'=>__('Austria','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['BB'] = array('name'=>__('Barbados','Shopp'),'currency'=>array('code'=>'BBD','format'=>'$#,###.##'),'units'=>'metric','region'=>0);
		$_['BS'] = array('name'=>__('Bahamas','Shopp'),'currency'=>array('code'=>'BSD','format'=>'$#,###.##'),'units'=>'metric','region'=>0);
		$_['BH'] = array('name'=>__('Bahrain','Shopp'),'currency'=>array('code'=>'BHD','format'=>'ب.د #,###.##'),'units'=>'metric','region'=>0);
		$_['BE'] = array('name'=>__('Belgium','Shopp'),'currency'=>array('code'=>'EUR','format'=>'#.###,## €'),'units'=>'metric','region'=>3);
		$_['BM'] = array('name'=>__('Bermuda','Shopp'),'currency'=>array('code'=>'BMD','format'=>'$#,###.##'),'units'=>'metric','region'=>0);
		$_['BR'] = array('name'=>__('Brazil','Shopp'),'currency'=>array('code'=>'BRL','format'=>'R$#.###,##'),'units'=>'metric','region'=>2);
		$_['BG'] = array('name'=>__('Bulgaria','Shopp'),'currency'=>array('code'=>'BGN','format'=>'# ###,## лв.'),'units'=>'metric','region'=>3);
		$_['CL'] = array('name'=>__('Chile','Shopp'),'currency'=>array('code'=>'CLP','format'=>'$#.###,##'),'units'=>'metric','region'=>2);
		$_['CN'] = array('name'=>__('China','Shopp'),'currency'=>array('code'=>'CNY','format'=>'¥#,###.##'),'units'=>'metric','region'=>6);
		$_['CO'] = array('name'=>__('Colombia','Shopp'),'currency'=>array('code'=>'COP','format'=>'$#.###,##'),'units'=>'metric','region'=>2);
		$_['CR'] = array('name'=>__('Costa Rica','Shopp'),'currency'=>array('code'=>'CRC','format'=>'₡#.###,##'),'units'=>'metric','region'=>1);
		$_['HR'] = array('name'=>__('Croatia','Shopp'),'currency'=>array('code'=>'HRK','format'=>'#.###,## kn'),'units'=>'metric','region'=>3);
		$_['CY'] = array('name'=>__('Cyprus','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#.###,##'),'units'=>'metric','region'=>3);
		$_['CZ'] = array('name'=>__('Czech Republic','Shopp'),'currency'=>array('code'=>'CZK','format'=>'# ###,## Kč'),'units'=>'metric','region'=>3);
		$_['DK'] = array('name'=>__('Denmark','Shopp'),'currency'=>array('code'=>'DKK','format'=>'#.###,## kr'),'units'=>'metric','region'=>3);
		$_['DO'] = array('name'=>__('Dominican Republic','Shopp'),'currency'=>array('code'=>'DOP','format'=>'$#,###.##'),'units'=>'metric','region'=>1);
		$_['EC'] = array('name'=>__('Ecuador','Shopp'),'currency'=>array('code'=>'USD','format'=>'$#,###.##'),'units'=>'metric','region'=>2);
		$_['EG'] = array('name'=>__('Egypt','Shopp'),'currency'=>array('code'=>'EGP','format'=>'£#,###.##'),'units'=>'metric','region'=>5);
		$_['EE'] = array('name'=>__('Estonia','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['FI'] = array('name'=>__('Finland','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['FR'] = array('name'=>__('France','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['DE'] = array('name'=>__('Germany','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['GR'] = array('name'=>__('Greece','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['GP'] = array('name'=>__('Guadeloupe','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['GT'] = array('name'=>__('Guatemala','Shopp'),'currency'=>array('code'=>'GTQ','format'=>'Q#,###.##'),'units'=>'metric','region'=>1);
		$_['HK'] = array('name'=>__('Hong Kong','Shopp'),'currency'=>array('code'=>'HKD','format'=>'$#,###.##'),'units'=>'metric','region'=>6);
		$_['HU'] = array('name'=>__('Hungary','Shopp'),'currency'=>array('code'=>'HUF','format'=>'# ### ### Ft'),'units'=>'metric','region'=>3);
		$_['IS'] = array('name'=>__('Iceland','Shopp'),'currency'=>array('code'=>'ISK','format'=>'#.###.### kr.'),'units'=>'metric','region'=>3);
		$_['IN'] = array('name'=>__('India','Shopp'),'currency'=>array('code'=>'INR','format'=>'₨#,##,###.##'),'units'=>'metric','region'=>6);
		$_['ID'] = array('name'=>__('Indonesia','Shopp'),'currency'=>array('code'=>'IDR','format'=>'Rp #.###,##'),'units'=>'metric','region'=>7);
		$_['IE'] = array('name'=>__('Ireland','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['IL'] = array('name'=>__('Israel','Shopp'),'currency'=>array('code'=>'ILS','format'=>'₪ #,###.##'),'units'=>'metric','region'=>4);
		$_['IT'] = array('name'=>__('Italy','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['JM'] = array('name'=>__('Jamaica','Shopp'),'currency'=>array('code'=>'JMD','format'=>'$#,###.##'),'units'=>'metric','region'=>0);
		$_['JP'] = array('name'=>__('Japan','Shopp'),'currency'=>array('code'=>'JPY','format'=>'¥#,###,###'),'units'=>'metric','region'=>6);
		$_['JO'] = array('name'=>__('Jordan','Shopp'),'currency'=>array('code'=>'JOD','format'=>'#,###.## .د.أ'),'units'=>'metric','region'=>4);
		$_['LV'] = array('name'=>__('Latvia','Shopp'),'currency'=>array('code'=>'LVL','format'=>'# ###.## Ls'),'units'=>'metric','region'=>3);
		$_['LT'] = array('name'=>__('Lithuania','Shopp'),'currency'=>array('code'=>'LTL','format'=>'#.###,## Lt'),'units'=>'metric','region'=>3);
		$_['LU'] = array('name'=>__('Luxembourg','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['MY'] = array('name'=>__('Malaysia','Shopp'),'currency'=>array('code'=>'MYR','format'=>'RM#,###.##'),'units'=>'metric','region'=>6);
		$_['MT'] = array('name'=>__('Malta','Shopp'),'currency'=>array('code'=>'MTL','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['MX'] = array('name'=>__('Mexico','Shopp'),'currency'=>array('code'=>'MXN','format'=>'$#,###.##'),'units'=>'metric','region'=>0);
		$_['NL'] = array('name'=>__('Netherlands','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#.###,##'),'units'=>'metric','region'=>3);
		$_['NZ'] = array('name'=>__('New Zealand','Shopp'),'currency'=>array('code'=>'NZD','format'=>'$#,###.##'),'units'=>'metric','region'=>7);
		$_['NG'] = array('name'=>__('Nigeria','Shopp'),'currency'=>array('code'=>'NGN','format'=>'₦#,###.##'),'units'=>'metric','region'=>5);
		$_['NO'] = array('name'=>__('Norway','Shopp'),'currency'=>array('code'=>'NOK','format'=>'kr # ###,##'),'units'=>'metric','region'=>3);
		$_['PK'] = array('name'=>__('Pakistan','Shopp'),'currency'=>array('code'=>'PKR','format'=>'₨#,###.##'),'units'=>'metric','region'=>4);
		$_['PA'] = array('name'=>__('Panama','Shopp'),'currency'=>array('code'=>'USD','format'=>'$ #,###.##'),'units'=>'metric','region'=>1);
		$_['PE'] = array('name'=>__('Peru','Shopp'),'currency'=>array('code'=>'PEN','format'=>'S/. #,###.##'),'units'=>'metric','region'=>2);
		$_['PH'] = array('name'=>__('Philippines','Shopp'),'currency'=>array('code'=>'PHP','format'=>'Php #,###.##'),'units'=>'metric','region'=>6);
		$_['PL'] = array('name'=>__('Poland','Shopp'),'currency'=>array('code'=>'PLN','format'=>'#.###,## zł'),'units'=>'metric','region'=>3);
		$_['PT'] = array('name'=>__('Portugal','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['PR'] = array('name'=>__('Puerto Rico','Shopp'),'currency'=>array('code'=>'USD','format'=>'$#,###.##'),'units'=>'imperial','region'=>0);
		$_['RO'] = array('name'=>__('Romania','Shopp'),'currency'=>array('code'=>'ROL','format'=>'#.###,## lei'),'units'=>'metric','region'=>3);
		$_['RU'] = array('name'=>__('Russia','Shopp'),'currency'=>array('code'=>'RUB','format'=>'# ###,## руб'),'units'=>'metric','region'=>6);
		$_['SA'] = array('name'=>__('Saudi Arabia','Shopp'),'currency'=>array('code'=>'SAR','format'=>'﷼ #,###.##'),'units'=>'metric','region'=>4);
		$_['SG'] = array('name'=>__('Singapore','Shopp'),'currency'=>array('code'=>'SGD','format'=>'$#,###.##'),'units'=>'metric','region'=>6);
		$_['SK'] = array('name'=>__('Slovakia','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['SI'] = array('name'=>__('Slovenia','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#,###.##'),'units'=>'metric','region'=>3);
		$_['ZA'] = array('name'=>__('South Africa','Shopp'),'currency'=>array('code'=>'ZAR','format'=>'R# ###,##'),'units'=>'metric','region'=>5);
		$_['KR'] = array('name'=>__('South Korea','Shopp'),'currency'=>array('code'=>'KRW','format'=>'₩#,###.##'),'units'=>'metric','region'=>6);
		$_['ES'] = array('name'=>__('Spain','Shopp'),'currency'=>array('code'=>'EUR','format'=>'€#.###,##'),'units'=>'metric','region'=>3);
		$_['VC'] = array('name'=>__('St. Vincent','Shopp'),'currency'=>array('code'=>'XCD','format'=>'$#,###.##'),'units'=>'metric','region'=>6);
		$_['SE'] = array('name'=>__('Sweden','Shopp'),'currency'=>array('code'=>'SEK','format'=>'#.###,## kr'),'units'=>'metric','region'=>3);
		$_['CH'] = array('name'=>__('Switzerland','Shopp'),'currency'=>array('code'=>'CHF','format'=>"#'###.## CHF"),'units'=>'metric','region'=>3);
		$_['TW'] = array('name'=>__('Taiwan','Shopp'),'currency'=>array('code'=>'TWD','format'=>'NT$#,###.##'),'units'=>'metric','region'=>6);
		$_['TH'] = array('name'=>__('Thailand','Shopp'),'currency'=>array('code'=>'THB','format'=>'#,###.##฿'),'units'=>'metric','region'=>6);
		$_['TT'] = array('name'=>__('Trinidad and Tobago','Shopp'),'currency'=>array('code'=>'TTD','format'=>'TT$#,###.##'),'units'=>'metric','region'=>0);
		$_['TR'] = array('name'=>__('Turkey','Shopp'),'currency'=>array('code'=>'TRL','format'=>'#.###,## TL'),'units'=>'metric','region'=>4);
		$_['UA'] = array('name'=>__('Ukraine','Shopp'),'currency'=>array('code'=>'UAH','format'=>'# ###,## ₴'),'units'=>'metric','region'=>4);
		$_['AE'] = array('name'=>__('United Arab Emirates','Shopp'),'currency'=>array('code'=>'AED','format'=>'Dhs. #,###.##'),'units'=>'metric','region'=>4);
		$_['UY'] = array('name'=>__('Uruguay','Shopp'),'currency'=>array('code'=>'UYP','format'=>'$#,###.##'),'units'=>'metric','region'=>2);
		$_['VI'] = array('name'=>__('Vietnam','Shopp'),'currency'=>array('code'=>'VND','format'=>'₫ #.###,##'),'units'=>'metric','region'=>6);
		$_['VE'] = array('name'=>__('Venezuela','Shopp'),'currency'=>array('code'=>'VUB','format'=>'Bs. #,###.##'),'units'=>'metric','region'=>2);
		return apply_filters('shopp_countries',$_);
	}

	/**
	 * Provides a lookup table of country zones (states/provinces)
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array
	 **/
	static function country_zones () {
		$_ = array();
		$_['AU'] = array();
		$_['AU']['ACT'] = 'Australian Capital Territory';
		$_['AU']['NSW'] = 'New South Wales';
		$_['AU']['NT'] = 'Northern Territory';
		$_['AU']['QLD'] = 'Queensland';
		$_['AU']['SA'] = 'South Australia';
		$_['AU']['TAS'] = 'Tasmania';
		$_['AU']['VIC'] = 'Victoria';
		$_['AU']['WA'] = 'Western Australia';

		$_['CA'] = array();
		$_['CA']['AB'] = 'Alberta';
		$_['CA']['BC'] = 'British Columbia';
		$_['CA']['MB'] = 'Manitoba';
		$_['CA']['NB'] = 'New Brunswick';
		$_['CA']['NF'] = 'Newfoundland';
		$_['CA']['NT'] = 'Northwest Territories';
		$_['CA']['NS'] = 'Nova Scotia';
		$_['CA']['NU'] = 'Nunavut';
		$_['CA']['ON'] = 'Ontario';
		$_['CA']['PE'] = 'Prince Edward Island';
		$_['CA']['PQ'] = 'Quebec';
		$_['CA']['SK'] = 'Saskatchewan';
		$_['CA']['YT'] = 'Yukon Territory';

		$_['US'] = array();
		$_['US']['AL'] = 'Alabama';
		$_['US']['AK'] = 'Alaska ';
		$_['US']['AZ'] = 'Arizona';
		$_['US']['AR'] = 'Arkansas';
		$_['US']['CA'] = 'California';
		$_['US']['CO'] = 'Colorado';
		$_['US']['CT'] = 'Connecticut';
		$_['US']['DE'] = 'Delaware';
		$_['US']['DC'] = 'District Of Columbia';
		$_['US']['FL'] = 'Florida';
		$_['US']['GA'] = 'Georgia';
		$_['US']['HI'] = 'Hawaii';
		$_['US']['ID'] = 'Idaho';
		$_['US']['IL'] = 'Illinois';
		$_['US']['IN'] = 'Indiana';
		$_['US']['IA'] = 'Iowa';
		$_['US']['KS'] = 'Kansas';
		$_['US']['KY'] = 'Kentucky';
		$_['US']['LA'] = 'Louisiana';
		$_['US']['ME'] = 'Maine';
		$_['US']['MD'] = 'Maryland';
		$_['US']['MA'] = 'Massachusetts';
		$_['US']['MI'] = 'Michigan';
		$_['US']['MN'] = 'Minnesota';
		$_['US']['MS'] = 'Mississippi';
		$_['US']['MO'] = 'Missouri';
		$_['US']['MT'] = 'Montana';
		$_['US']['NE'] = 'Nebraska';
		$_['US']['NV'] = 'Nevada';
		$_['US']['NH'] = 'New Hampshire';
		$_['US']['NJ'] = 'New Jersey';
		$_['US']['NM'] = 'New Mexico';
		$_['US']['NY'] = 'New York';
		$_['US']['NC'] = 'North Carolina';
		$_['US']['ND'] = 'North Dakota';
		$_['US']['OH'] = 'Ohio';
		$_['US']['OK'] = 'Oklahoma';
		$_['US']['OR'] = 'Oregon';
		$_['US']['PA'] = 'Pennsylvania';
		$_['US']['RI'] = 'Rhode Island';
		$_['US']['SC'] = 'South Carolina';
		$_['US']['SD'] = 'South Dakota';
		$_['US']['TN'] = 'Tennessee';
		$_['US']['TX'] = 'Texas';
		$_['US']['UT'] = 'Utah';
		$_['US']['VT'] = 'Vermont';
		$_['US']['VA'] = 'Virginia';
		$_['US']['WA'] = 'Washington';
		$_['US']['WV'] = 'West Virginia';
		$_['US']['WI'] = 'Wisconsin';
		$_['US']['WY'] = 'Wyoming';

		$_['USAF']['AA'] = 'Americas';
		$_['USAF']['AE'] = 'Europe';
		$_['USAF']['AP'] = 'Pacific';
		return apply_filters('shopp_country_zones',$_);
	}

	/**
	 * Provides a lookup table of colloquial country areas codified by post code regions
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array
	 **/
	static function country_areas () {
		$_ = array();
		$_['CA'] = array();
		$_['CA']['Northern Canada'] = array('YT'=>array('Y'),'NT'=>array('X'),'NU'=>array('X'));
		$_['CA']['Western Canada'] = array('BC'=>array('V'),'AB'=>array('T'),'SK'=>array('S'),'MB'=>array('R'));
		$_['CA']['Eastern Canada'] = array('ON'=>array('K','L','M','N','P'),'PQ'=>array('G','H','J'),'NB'=>array('E'),'PE'=>array('C'),'NS'=>array('B'),'NF'=>array('A'));

		$_['US'] = array();
		$_['US']['Northeast US']	= array('MA','RI','NH','ME','VT','CT','NJ','NY','PA');
		$_['US']['Midwest US']		= array('OH','IN','MI','IA','WI','MN','SD','ND','IL','MO','KS','NE');
		$_['US']['South US'] 		= array('DE','DC','MD','VA','WV','NC','SC','GA','FL','AL','TN','MS','KY','LA','AR','OK','TX');
		$_['US']['West US'] 		= array('MT','CO','WY','ID','UT','AZ','NM','NV','CA','HI','OR','WA','AK');

		$_['USZIP'] = array(
		'005'=>'NY', '006'=>'PR', '007'=>'PR', '008'=>'VI', '009'=>'PR', '010'=>'MA', '011'=>'MA', '012'=>'MA', '013'=>'MA', '014'=>'MA', '015'=>'MA', '016'=>'MA', '017'=>'MA', '018'=>'MA', '019'=>'MA', '020'=>'MA', '021'=>'MA', '022'=>'MA', '023'=>'MA', '024'=>'MA', '025'=>'MA', '026'=>'MA', '027'=>'MA', '028'=>'RI', '029'=>'RI', '030'=>'NH', '031'=>'NH', '032'=>'NH', '033'=>'NH', '034'=>'NH', '035'=>'NH', '036'=>'NH', '037'=>'NH', '038'=>'NH', '039'=>'ME', '040'=>'ME', '041'=>'ME', '042'=>'ME', '043'=>'ME', '044'=>'ME', '045'=>'ME', '046'=>'ME', '047'=>'ME', '048'=>'ME', '049'=>'ME', '050'=>'VT', '051'=>'VT', '052'=>'VT', '053'=>'VT', '054'=>'VT', '055'=>'MA', '056'=>'VT', '057'=>'VT', '058'=>'VT', '059'=>'VT', '060'=>'CT', '061'=>'CT', '062'=>'CT', '063'=>'CT', '064'=>'CT', '065'=>'CT', '066'=>'CT', '067'=>'CT', '068'=>'CT', '069'=>'CT', '070'=>'NJ', '071'=>'NJ', '072'=>'NJ', '073'=>'NJ', '074'=>'NJ', '075'=>'NJ', '076'=>'NJ', '077'=>'NJ', '078'=>'NJ', '079'=>'NJ', '080'=>'NJ', '081'=>'NJ', '082'=>'NJ', '083'=>'NJ', '084'=>'NJ', '085'=>'NJ', '086'=>'NJ', '087'=>'NJ', '088'=>'NJ', '089'=>'NJ', '090'=>'AE', '091'=>'AE', '092'=>'AE', '093'=>'AE', '094'=>'AE', '095'=>'AE', '096'=>'AE', '097'=>'AE', '098'=>'AE', '100'=>'NY', '101'=>'NY', '102'=>'NY', '103'=>'NY', '104'=>'NY', '105'=>'NY', '106'=>'NY', '107'=>'NY', '108'=>'NY', '109'=>'NY', '110'=>'NY', '111'=>'NY', '112'=>'NY', '113'=>'NY', '114'=>'NY', '115'=>'NY', '116'=>'NY', '117'=>'NY', '118'=>'NY', '119'=>'NY', '120'=>'NY', '121'=>'NY', '122'=>'NY', '123'=>'NY', '124'=>'NY', '125'=>'NY', '126'=>'NY', '127'=>'NY', '128'=>'NY', '129'=>'NY', '130'=>'NY', '131'=>'NY', '132'=>'NY', '133'=>'NY', '134'=>'NY', '135'=>'NY', '136'=>'NY', '137'=>'NY', '138'=>'NY', '139'=>'NY', '140'=>'NY', '141'=>'NY', '142'=>'NY', '143'=>'NY', '144'=>'NY', '145'=>'NY', '146'=>'NY', '147'=>'NY', '148'=>'NY', '149'=>'NY', '150'=>'PA', '151'=>'PA', '152'=>'PA', '153'=>'PA', '154'=>'PA', '155'=>'PA', '156'=>'PA', '157'=>'PA', '158'=>'PA', '159'=>'PA', '160'=>'PA', '161'=>'PA', '162'=>'PA', '163'=>'PA', '164'=>'PA', '165'=>'PA', '166'=>'PA', '167'=>'PA', '168'=>'PA', '169'=>'PA', '170'=>'PA', '171'=>'PA', '172'=>'PA', '173'=>'PA', '174'=>'PA', '175'=>'PA', '176'=>'PA', '177'=>'PA', '178'=>'PA', '179'=>'PA', '180'=>'PA', '181'=>'PA', '182'=>'PA', '183'=>'PA', '184'=>'PA', '185'=>'PA', '186'=>'PA', '187'=>'PA', '188'=>'PA', '189'=>'PA', '190'=>'PA', '191'=>'PA', '192'=>'PA', '193'=>'PA', '194'=>'PA', '195'=>'PA', '196'=>'PA', '197'=>'DE', '198'=>'DE', '199'=>'DE', '200'=>'DC', '201'=>'VA', '202'=>'DC', '203'=>'DC', '204'=>'DC', '205'=>'DC', '206'=>'MD', '207'=>'MD', '208'=>'MD', '209'=>'MD', '210'=>'MD', '211'=>'MD', '212'=>'MD', '214'=>'MD', '215'=>'MD', '216'=>'MD', '217'=>'MD', '218'=>'MD', '219'=>'MD', '220'=>'VA', '221'=>'VA', '222'=>'VA', '223'=>'VA', '224'=>'VA', '225'=>'VA', '226'=>'VA', '227'=>'VA', '228'=>'VA', '229'=>'VA', '230'=>'VA', '231'=>'VA', '232'=>'VA', '233'=>'VA', '234'=>'VA', '235'=>'VA', '236'=>'VA', '237'=>'VA', '238'=>'VA', '239'=>'VA', '240'=>'VA', '241'=>'VA', '242'=>'VA', '243'=>'VA', '244'=>'VA', '245'=>'VA', '246'=>'VA', '247'=>'WV', '248'=>'WV', '249'=>'WV', '250'=>'WV', '251'=>'WV', '252'=>'WV', '253'=>'WV', '254'=>'WV', '255'=>'WV', '256'=>'WV', '257'=>'WV', '258'=>'WV', '259'=>'WV', '260'=>'WV', '261'=>'WV', '262'=>'WV', '263'=>'WV', '264'=>'WV', '265'=>'WV', '266'=>'WV', '267'=>'WV', '268'=>'WV', '270'=>'NC', '271'=>'NC', '272'=>'NC', '273'=>'NC', '274'=>'NC', '275'=>'NC', '276'=>'NC', '277'=>'NC', '278'=>'NC', '279'=>'NC', '280'=>'NC', '281'=>'NC', '282'=>'NC', '283'=>'NC', '284'=>'NC', '285'=>'NC', '286'=>'NC', '287'=>'NC', '288'=>'NC', '289'=>'NC', '290'=>'SC', '291'=>'SC', '292'=>'SC', '293'=>'SC', '294'=>'SC', '295'=>'SC', '296'=>'SC', '297'=>'SC', '298'=>'SC', '299'=>'SC', '300'=>'GA', '301'=>'GA', '302'=>'GA', '303'=>'GA', '304'=>'GA', '305'=>'GA', '306'=>'GA', '307'=>'GA', '308'=>'GA', '309'=>'GA', '310'=>'GA', '311'=>'GA', '312'=>'GA', '313'=>'GA', '314'=>'GA', '315'=>'GA', '316'=>'GA', '317'=>'GA', '318'=>'GA', '319'=>'GA', '320'=>'FL', '321'=>'FL', '322'=>'FL', '323'=>'FL', '324'=>'FL', '325'=>'FL', '326'=>'FL', '327'=>'FL', '328'=>'FL', '329'=>'FL', '330'=>'FL', '331'=>'FL', '332'=>'FL', '333'=>'FL', '334'=>'FL', '335'=>'FL', '336'=>'FL', '337'=>'FL', '338'=>'FL', '339'=>'FL', '340'=>'AA', '341'=>'FL', '342'=>'FL', '344'=>'FL', '346'=>'FL', '347'=>'FL', '349'=>'FL', '350'=>'AL', '351'=>'AL', '352'=>'AL', '354'=>'AL', '355'=>'AL', '356'=>'AL', '357'=>'AL', '358'=>'AL', '359'=>'AL', '360'=>'AL', '361'=>'AL', '362'=>'AL', '363'=>'AL', '364'=>'AL', '365'=>'AL', '366'=>'AL', '367'=>'AL', '368'=>'AL', '369'=>'AL', '370'=>'TN', '371'=>'TN', '372'=>'TN', '373'=>'TN', '374'=>'TN', '375'=>'TN', '376'=>'TN', '377'=>'TN', '378'=>'TN', '379'=>'TN', '380'=>'TN', '381'=>'TN', '382'=>'TN', '383'=>'TN', '384'=>'TN', '385'=>'TN', '386'=>'MS', '387'=>'MS', '388'=>'MS', '389'=>'MS', '390'=>'MS', '391'=>'MS', '392'=>'MS', '393'=>'MS', '394'=>'MS', '395'=>'MS', '396'=>'MS', '397'=>'MS', '398'=>'GA', '399'=>'GA', '400'=>'KY', '401'=>'KY', '402'=>'KY', '403'=>'KY', '404'=>'KY', '405'=>'KY', '406'=>'KY', '407'=>'KY', '408'=>'KY', '409'=>'KY', '410'=>'KY', '411'=>'KY', '412'=>'KY', '413'=>'KY', '414'=>'KY', '415'=>'KY', '416'=>'KY', '417'=>'KY', '418'=>'KY', '420'=>'KY', '421'=>'KY', '422'=>'KY', '423'=>'KY', '424'=>'KY', '425'=>'KY', '426'=>'KY', '427'=>'KY', '430'=>'OH', '431'=>'OH', '432'=>'OH', '433'=>'OH', '434'=>'OH', '435'=>'OH', '436'=>'OH', '437'=>'OH', '438'=>'OH', '439'=>'OH', '440'=>'OH', '441'=>'OH', '442'=>'OH', '443'=>'OH', '444'=>'OH', '445'=>'OH', '446'=>'OH', '447'=>'OH', '448'=>'OH', '449'=>'OH', '450'=>'OH', '451'=>'OH', '452'=>'OH', '453'=>'OH', '454'=>'OH', '455'=>'OH', '456'=>'OH', '457'=>'OH', '458'=>'OH', '459'=>'OH', '460'=>'IN', '461'=>'IN', '462'=>'IN', '463'=>'IN', '464'=>'IN', '465'=>'IN', '466'=>'IN', '467'=>'IN', '468'=>'IN', '469'=>'IN', '470'=>'IN', '471'=>'IN', '472'=>'IN', '473'=>'IN', '474'=>'IN', '475'=>'IN', '476'=>'IN', '477'=>'IN', '478'=>'IN', '479'=>'IN', '480'=>'MI', '481'=>'MI', '482'=>'MI', '483'=>'MI', '484'=>'MI', '485'=>'MI', '486'=>'MI', '487'=>'MI', '488'=>'MI', '489'=>'MI', '490'=>'MI', '491'=>'MI', '492'=>'MI', '493'=>'MI', '494'=>'MI', '495'=>'MI', '496'=>'MI', '497'=>'MI', '498'=>'MI', '499'=>'MI', '500'=>'IA', '501'=>'IA', '502'=>'IA', '503'=>'IA', '504'=>'IA', '505'=>'IA', '506'=>'IA', '507'=>'IA', '508'=>'IA', '509'=>'IA', '510'=>'IA', '511'=>'IA', '512'=>'IA', '513'=>'IA', '514'=>'IA', '515'=>'IA', '516'=>'IA', '520'=>'IA', '521'=>'IA', '522'=>'IA', '523'=>'IA', '524'=>'IA', '525'=>'IA', '526'=>'IA', '527'=>'IA', '528'=>'IA', '530'=>'WI', '531'=>'WI', '532'=>'WI', '534'=>'WI', '535'=>'WI', '537'=>'WI', '538'=>'WI', '539'=>'WI', '540'=>'WI', '541'=>'WI', '542'=>'WI', '543'=>'WI', '544'=>'WI', '545'=>'WI', '546'=>'WI', '547'=>'WI', '548'=>'WI', '549'=>'WI', '550'=>'MN', '551'=>'MN', '553'=>'MN', '554'=>'MN', '555'=>'MN', '556'=>'MN', '557'=>'MN', '558'=>'MN', '559'=>'MN', '560'=>'MN', '561'=>'MN', '562'=>'MN', '563'=>'MN', '564'=>'MN', '565'=>'MN', '566'=>'MN', '567'=>'MN', '569'=>'DC', '570'=>'SD', '571'=>'SD', '572'=>'SD', '573'=>'SD', '574'=>'SD', '575'=>'SD', '576'=>'SD', '577'=>'SD', '580'=>'ND', '581'=>'ND', '582'=>'ND', '583'=>'ND', '584'=>'ND', '585'=>'ND', '586'=>'ND', '587'=>'ND', '588'=>'ND', '590'=>'MT', '591'=>'MT', '592'=>'MT', '593'=>'MT', '594'=>'MT', '595'=>'MT', '596'=>'MT', '597'=>'MT', '598'=>'MT', '599'=>'MT', '600'=>'IL', '601'=>'IL', '602'=>'IL', '603'=>'IL', '604'=>'IL', '605'=>'IL', '606'=>'IL', '607'=>'IL', '608'=>'IL', '609'=>'IL', '610'=>'IL', '611'=>'IL', '612'=>'IL', '613'=>'IL', '614'=>'IL', '615'=>'IL', '616'=>'IL', '617'=>'IL', '618'=>'IL', '619'=>'IL', '620'=>'IL', '622'=>'IL', '623'=>'IL', '624'=>'IL', '625'=>'IL', '626'=>'IL', '627'=>'IL', '628'=>'IL', '629'=>'IL', '630'=>'MO', '631'=>'MO', '633'=>'MO', '634'=>'MO', '635'=>'MO', '636'=>'MO', '637'=>'MO', '638'=>'MO', '639'=>'MO', '640'=>'MO', '641'=>'MO', '644'=>'MO', '645'=>'MO', '646'=>'MO', '647'=>'MO', '648'=>'MO', '649'=>'MO', '650'=>'MO', '651'=>'MO', '652'=>'MO', '653'=>'MO', '654'=>'MO', '655'=>'MO', '656'=>'MO', '657'=>'MO', '658'=>'MO', '660'=>'KS', '661'=>'KS', '662'=>'KS', '664'=>'KS', '665'=>'KS', '666'=>'KS', '667'=>'KS', '668'=>'KS', '669'=>'KS', '670'=>'KS', '671'=>'KS', '672'=>'KS', '673'=>'KS', '674'=>'KS', '675'=>'KS', '676'=>'KS', '677'=>'KS', '678'=>'KS', '679'=>'KS', '680'=>'NE', '681'=>'NE', '683'=>'NE', '684'=>'NE', '685'=>'NE', '686'=>'NE', '687'=>'NE', '688'=>'NE', '689'=>'NE', '690'=>'NE', '691'=>'NE', '692'=>'NE', '693'=>'NE', '700'=>'LA', '701'=>'LA', '703'=>'LA', '704'=>'LA', '705'=>'LA', '706'=>'LA', '707'=>'LA', '708'=>'LA', '710'=>'LA', '711'=>'LA', '712'=>'LA', '713'=>'LA', '714'=>'LA', '716'=>'AR', '717'=>'AR', '718'=>'AR', '719'=>'AR', '720'=>'AR', '721'=>'AR', '722'=>'AR', '723'=>'AR', '724'=>'AR', '725'=>'AR', '726'=>'AR', '727'=>'AR', '728'=>'AR', '729'=>'AR', '730'=>'OK', '731'=>'OK', '733'=>'TX', '734'=>'OK', '735'=>'OK', '736'=>'OK', '737'=>'OK', '738'=>'OK', '739'=>'OK', '740'=>'OK', '741'=>'OK', '743'=>'OK', '744'=>'OK', '745'=>'OK', '746'=>'OK', '747'=>'OK', '748'=>'OK', '749'=>'OK', '750'=>'TX', '751'=>'TX', '752'=>'TX', '753'=>'TX', '754'=>'TX', '755'=>'TX', '756'=>'TX', '757'=>'TX', '758'=>'TX', '759'=>'TX', '760'=>'TX', '761'=>'TX', '762'=>'TX', '763'=>'TX', '764'=>'TX', '765'=>'TX', '766'=>'TX', '767'=>'TX', '768'=>'TX', '769'=>'TX', '770'=>'TX', '772'=>'TX', '773'=>'TX', '774'=>'TX', '775'=>'TX', '776'=>'TX', '777'=>'TX', '778'=>'TX', '779'=>'TX', '780'=>'TX', '781'=>'TX', '782'=>'TX', '783'=>'TX', '784'=>'TX', '785'=>'TX', '786'=>'TX', '787'=>'TX', '788'=>'TX', '789'=>'TX', '790'=>'TX', '791'=>'TX', '792'=>'TX', '793'=>'TX', '794'=>'TX', '795'=>'TX', '796'=>'TX', '797'=>'TX', '798'=>'TX', '799'=>'TX', '800'=>'CO', '801'=>'CO', '802'=>'CO', '803'=>'CO', '804'=>'CO', '805'=>'CO', '806'=>'CO', '807'=>'CO', '808'=>'CO', '809'=>'CO', '810'=>'CO', '811'=>'CO', '812'=>'CO', '813'=>'CO', '814'=>'CO', '815'=>'CO', '816'=>'CO', '820'=>'WY', '821'=>'WY', '822'=>'WY', '823'=>'WY', '824'=>'WY', '825'=>'WY', '826'=>'WY', '827'=>'WY', '828'=>'WY', '829'=>'WY', '830'=>'WY', '831'=>'WY', '832'=>'ID', '833'=>'ID', '834'=>'ID', '835'=>'ID', '836'=>'ID', '837'=>'ID', '838'=>'ID', '840'=>'UT', '841'=>'UT', '842'=>'UT', '843'=>'UT', '844'=>'UT', '845'=>'UT', '846'=>'UT', '847'=>'UT', '850'=>'AZ', '851'=>'AZ', '852'=>'AZ', '853'=>'AZ', '855'=>'AZ', '856'=>'AZ', '857'=>'AZ', '859'=>'AZ', '860'=>'AZ', '863'=>'AZ', '864'=>'AZ', '865'=>'AZ', '870'=>'NM', '871'=>'NM', '872'=>'NM', '873'=>'NM', '874'=>'NM', '875'=>'NM', '877'=>'NM', '878'=>'NM', '879'=>'NM', '880'=>'NM', '881'=>'NM', '882'=>'NM', '883'=>'NM', '884'=>'NM', '885'=>'TX', '889'=>'NV', '890'=>'NV', '891'=>'NV', '893'=>'NV', '894'=>'NV', '895'=>'NV', '897'=>'NV', '898'=>'NV', '900'=>'CA', '901'=>'CA', '902'=>'CA', '903'=>'CA', '904'=>'CA', '905'=>'CA', '906'=>'CA', '907'=>'CA', '908'=>'CA', '910'=>'CA', '911'=>'CA', '912'=>'CA', '913'=>'CA', '914'=>'CA', '915'=>'CA', '916'=>'CA', '917'=>'CA', '918'=>'CA', '919'=>'CA', '920'=>'CA', '921'=>'CA', '922'=>'CA', '923'=>'CA', '924'=>'CA', '925'=>'CA', '926'=>'CA', '927'=>'CA', '928'=>'CA', '930'=>'CA', '931'=>'CA', '932'=>'CA', '933'=>'CA', '934'=>'CA', '935'=>'CA', '936'=>'CA', '937'=>'CA', '938'=>'CA', '939'=>'CA', '940'=>'CA', '941'=>'CA', '942'=>'CA', '943'=>'CA', '944'=>'CA', '945'=>'CA', '946'=>'CA', '947'=>'CA', '948'=>'CA', '949'=>'CA', '950'=>'CA', '951'=>'CA', '952'=>'CA', '953'=>'CA', '954'=>'CA', '955'=>'CA', '956'=>'CA', '957'=>'CA', '958'=>'CA', '959'=>'CA', '960'=>'CA', '961'=>'CA', '962'=>'AP', '963'=>'AP', '964'=>'AP', '965'=>'AP', '966'=>'AP', '967'=>'HI', '968'=>'HI', '969'=>'GU', '970'=>'OR', '971'=>'OR', '972'=>'OR', '973'=>'OR', '974'=>'OR', '975'=>'OR', '976'=>'OR', '977'=>'OR', '978'=>'OR', '979'=>'OR', '980'=>'WA', '981'=>'WA', '982'=>'WA', '983'=>'WA', '984'=>'WA', '985'=>'WA', '986'=>'WA', '988'=>'WA', '989'=>'WA', '990'=>'WA', '991'=>'WA', '992'=>'WA', '993'=>'WA', '994'=>'WA', '995'=>'AK', '996'=>'AK', '997'=>'AK', '998'=>'AK', '999'=>'AK');

		$_['USAF'] = array();
		$_['USAF']['Americas'] = array('AA');
		$_['USAF']['Europe'] = array('AE');
		$_['USAF']['Pacific'] = array('AP');

		return apply_filters('shopp_areas',$_);
	}

	function customer_types () {
		$_ = array(
			__('Retail','Shopp'),
			__('Wholesale','Shopp'),
			__('Referral','Shopp'),
			__('Tax-Exempt','Shopp')
		);
		return apply_filters('shopp_customer_types',$_);
	}


	function localities () {
		$_ = array();
		return apply_filters('shopp_localities',$_);
	}

	/**
	 * Provides a list of country codes for countries that use VAT taxes
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of country codes
	 **/
	static function vat_countries () {
		return apply_filters('shopp_vat_countries',array(
			'AU','AT','BE','BG','CZ','DK','DE','EE','GB',
			'GR','ES','FR','IE','IT','CY','LV','LT','LU',
			'HU','MT','NL','PL','PT','RO','SI','SK','FI',
			'SE'
		));
	}

	/**
	 * Provides a list of supported payment cards
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of payment cards
	 **/
	static function paycards () {
		$_ = array();
		$_['amex'] = new PayCard('American Express','Amex','/^3[4,7]\d{13}$/',4);
		$_['dc'] = new PayCard("Diner's Club",'DC','/^(30|36|38|39|54)\d{12}$/',3);
		$_['disc'] = new PayCard("Discover Card",'Disc','/^6(011|22[0-9]|4[4-9]0|5[0-9][0-9])\d{12}$/',3);
		$_['jcb'] = new PayCard('JCB','JCB','/^35(2[8-9]|[3-8][0-9])\d{12}$/',3);
		$_['lasr'] = new PayCard('Laser','Lasr','/^(6304|6706|6709|6771)\d{12,15}$/');
		$_['maes'] = new PayCard('Maestro','Maes','/^(311|367|[5-6][0-9][0-9][0-9])\d{8,15}$/',3, array('start'=>5,'issue'=>3));
		$_['mc'] = new PayCard('MasterCard','MC','/^5[1-5]\d{14}$/',3);
		$_['solo'] = new PayCard('Solo','Solo','/^(6334|6767)(\d{12}|\d{14,15})$/',3, array('start'=>5,'issue'=>3));
		$_['visa'] = new PayCard('Visa','Visa','/^4\d{15}$/',3);
		return apply_filters('shopp_payment_cards',$_);
	}

	/**
	 * Gets a specified payment card
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return object PayCard object
	 **/
	static function paycard ($card) {
		$cards = Lookup::paycards();
		if (isset($cards[strtolower($card)])) return $cards[strtolower($card)];
		return false;
	}

	/**
	 * A list of translatable payment status labels
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	static function payment_status_labels () {
		$_ = array(
			'PENDING' => __('Pending','Shopp'),
			'CHARGED' => __('Charged','Shopp'),
			'REFUNDED' => __('Refunded','Shopp'),
			'VOID' => __('Void','Shopp')
		);
		return apply_filters('shopp_payment_status_labels',$_);
	}

	/**
	 * A list of stop words to be excluded from search indexes
	 *
	 * Stop words are commonly used words that are not particularly
	 * useful for searching because they would provide too much
	 * noise (irrelevant hits) in the results
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of stop words
	 **/
	static function stopwords () {
		$_ = array(
	  	    "a", "an", "and", "are", "as", "at", "be", "but", "by",
		    "for", "if", "in", "into", "is", "it",
		    "no", "not", "of", "on", "or", "such",
		    "that", "the", "their", "then", "there", "these",
		    "they", "this", "to", "was", "will", "with"
		);
		return apply_filters('shopp_index_stopwords',$_);
	}

	/**
	 * Provides index factor settings to use when building indexes
	 *
	 * Index factoring provides a configurable set of relevancy weights
	 * that are factored into the scoring of search results. Factors are
	 * in percentages, thus a factor of 50 gives the index half the
	 * relevancy of a normal index. Searching on an index with a factor
	 * of 200 doubles the relevancy of hits on matches in that index.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of index factor settings
	 **/
	static function index_factors () {
		$_ = array(
			'name' => 200,
			'prices' => 160,
			'specs' => 75,
			'summary' => 100,
			'description' => 100,
			'categories' => 50,
			'tags' => 50
		);
		return apply_filters('shopp_index_factors',$_);
	}


} // END class Lookup

?>