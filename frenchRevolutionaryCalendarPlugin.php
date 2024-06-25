<?php
/* ----------------------------------------------------------------------
 * frenchRevolutionaryCalendarPlugin.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2012 Whirl-i-Gig
 *
 * This plugin is created and maitained by IdéesCulture
 * www.ideesculture.com
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
require_once(__CA_LIB_DIR__."/Parsers/TimeExpressionParser.php");

if (!function_exists('str_ends_with')) {
  function str_ends_with($str, $end) {
    return (@substr_compare($str, $end, -strlen($end))==0);
  }
}

class frenchRevolutionaryCalendarPlugin extends BaseApplicationPlugin {
		# -------------------------------------------------------
		private $opo_config;
		private $ops_plugin_path;
		private $opa_monthnames;

		private $opo_language_settings;

		public $description;

		# -------------------------------------------------------
		public function __construct($ps_plugin_path) {
			global $g_ui_locale;

			$this->description = _t('Handles French Revolutionary calendar dates as input for CollectiveAccess TimeExpressionParser');
			$this->ops_plugin_path = $ps_plugin_path;
			parent::__construct();
			$this->opo_config = Configuration::load($ps_plugin_path.'/conf/frenchRevolutionaryCalendar.conf');

			$this->opa_monthnames = array(
				"vendemiaire",
				"brumaire",
				"frimaire",
				"nivose",
				"pluviose",
				"ventose",
				"germinal",
				"floreal",
				"prairial",
				"messidor",
				"thermidor",
				"fructidor",
				"sansculottide",
				"sans-culottide",
				"jour complémentaire"
			);

			$ps_iso_code = $g_ui_locale;
			if (!$ps_iso_code) { $ps_iso_code = 'fr_FR'; }
			if (file_exists(__CA_LIB_DIR__.'/Parsers/TimeExpressionParser/'.$ps_iso_code.'.lang')) {
				$this->opo_language_settings = Configuration::load(__CA_LIB_DIR__.'/Parsers/TimeExpressionParser/'.$ps_iso_code.'.lang');
			} else {
				die("Could not load language '$ps_iso_code'");
			}
		}
		# -------------------------------------------------------
		/**
		 * Override checkStatus() to return true - the twitterPlugin plugin always initializes ok
		 */
		public function checkStatus() {
			return array(
				'description' => $this->getDescription(),
				'errors' => array(),
				'warnings' => array(),
				'available' => ((bool)$this->opo_config->get('enabled'))
			);
		}
		# -------------------------------------------------------
		/**
		 * Perform client services-related periodic tasks
		 */
		public function hookTimeExpressionParserPreprocessAfter(array $pa_params=array()) {
			if(!$this->opo_config->get('enabled')) return false;

			$vb_month_comes_first = $this->opo_language_settings->get('monthComesFirstInDelimitedDate');

			if(isset($pa_params["expression"])) {
				
				// coding here the decryption to normal dates
				$vs_month_exp = implode("|",$this->opa_monthnames);

				// extracting from params the time expression
				$vs_expression=$pa_params["expression"];

				// remove accents
				$vs_expression=caRemoveAccents($vs_expression);

				if (((bool)$this->opo_config->get('removeSquareBrackets'))) {
					$pa_params["expression"] = substr($pa_params["expression"] , 0, -2);
				}
				if (((bool)$this->opo_config->get('interrogationEqualsCirca'))) {	
					if(str_ends_with($pa_params["expression"], " ?")) {
						$pa_params["expression"] = str_replace($pa_params["expression"]);
					}
				}
				// Traitement des " et s. d." de la Fondation Chambrun dans la numérisation 2020.
				$vs_expression = str_replace("s. d.", "s.d.", $vs_expression);
				$vs_expression = str_replace("- s.d.", " et s.d.", $vs_expression);
				$vs_expression = str_replace("-s.d.", " et s.d.", $vs_expression);
				$vs_expression = str_replace("s.d.-", "circa ", $vs_expression);
				if(strpos($vs_expression, " et s. d.") !== false) {
					$vs_expression = str_replace(" et s. d.", "", $vs_expression);
				}

				// if no month name/"ere republicaine" inside the expression, direct exit
				if(
					!preg_match("/".$vs_month_exp."/i",$vs_expression) 
					&&
					!preg_match("/ere republicaine/i",caRemoveAccents($vs_expression)) 
				) {
					return $pa_params;
				}
				
				// remove extra precision
				$vs_expression = trim(str_ireplace(["républicaine","republicaine"], "", $vs_expression));
				$vs_expression = trim(str_ireplace(["de l'ere","de l'ère"], "", $vs_expression));
				$vs_expression = trim(str_ireplace(["ere","ère"], "", $vs_expression));
				
				// If 2 parts expression
				if(strpos($vs_expression, "-") !== false) {
					$va_expression = explode("-",$vs_expression);
				} else {
					// Single part expression

					// Check if we have no day precision like "vendémiaire an 2"
					$vb_has_no_day = preg_match("/^(?<month>".$vs_month_exp.") (?:an )?(?<year_roman>[IXVLCDM]+)?(?<year_dec>[0-9]+)?/i",$vs_expression,$va_results);
					if($vb_has_no_day) {
						$vs_expression = "1 ".$vs_expression."-30 ".$vs_expression;
						$va_expression = explode("-",$vs_expression);
					} else {
						$vb_has_no_month = preg_match("/^(?:an )?(?<year_roman>[IXVLCDM]+)?(?<year_dec>[0-9]+)?/i",$vs_expression,$va_results);
						if($vb_has_no_month) {
							$vs_expression = "1 vendémiaire ".$vs_expression."-30 sansculottide ".$vs_expression;
							$va_expression = explode("-",$vs_expression);
						}
						$va_expression= array($vs_expression);
					}
				}

				// Treat each part of expression
				foreach($va_expression as $num=>$vs_expression_part) {

					// Regexp : this is where the magic occurs
					preg_match("/(?:(?<day>\d{1,2})([erm\s]+?))?(?<month>".$vs_month_exp.") (?:an\s?)?(?<year_roman>[IXVLCDM]+)?(?<year_dec>[0-9]+)?/i",$vs_expression_part,$va_results);

					// Year can be typed in roman number of in decimal, with an option "an" (year) keyword before the year entry
					$vi_year = (caRomanArabic($va_results["year_roman"]) ? : $va_results["year_dec"]);
					$vs_gregorian_date = jdtogregorian(
						frenchtojd(
							(array_search($va_results["month"], $this->opa_monthnames)+ 1),
							$va_results["day"],
							$vi_year
						)
					);

					// Change month order if locale requires it
					if(!$vb_month_comes_first) {
						// Date from jdtogregorian comes always with month first, reordering it
						$va_date_parts=explode("/",$vs_gregorian_date);
						$vs_gregorian_date=$va_date_parts[1]."/".$va_date_parts[0]."/".$va_date_parts[2];
					}

					// Replacing the date
					$va_expression[$num] = $vs_gregorian_date;
				}
				// Merge parts
				if (sizeof($va_expression)>1) {
					$vs_expression=implode(" - ",$va_expression);
				} else {
					$vs_expression=$va_expression[0];
				}
				$pa_params["expression"] = $vs_expression;
			}
			return $pa_params;
		}

		# -------------------------------------------------------
		/**
		 * Get plugin user actions
		 */
		static public function getRoleActionList() {
			return array();
		}
		# -------------------------------------------------------
	}
