<?php
/*************************************************************************************/
/*         ### Report MAJ information train de la Gare SNCF ###                      */
/*                                                                                   */
/*                     Développement par eedomusbox@gmail.com                        */
/*                            Version 1.1                                            */
/*************************************************************************************/

/*************************************** API eedomus ********************************/
// Identifiants de l'API Eedomus
$api_user  = "XXXXXX";
$api_secret= "YYYYYYYYYYY";
$IPeedomus = "api.eedomus.com"; 
$IPLocal   = "192.168.0.XX/api";
$periph_id = 'ZZZZZZZ';

// Identifiants de l'API SNCF https://data.sncf.com/api/fr/documentation
$api_sncf_user = 'TTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTT';
$api_sncf_mdp  = '';

// Initialiation des variables
// Pour trouver l'id de la ville rechercher "id" dans la requete suivante https://api.sncf.com/v1/coverage/sncf/places?q=LYON
$VilleDepart  = 'admin:139203extern';
$VilleArrivee = 'admin:117905extern';
$result = '';

// Récupération des paramètres de la requête : 2 methodes, une Web et une Raspberry
if (isset($_GET['trajet'])) {
    $SensTrajet = $_GET['trajet'];
	$rChariot = "<BR>";  //Retour à la ligne
} else {
	$SensTrajet = $_SERVER['argv'][1];
	$rChariot = "\n"; //Retour à la ligne
}
 
//******************************** Contruction du trajet *****************************/
switch ($SensTrajet) {
	case 'a':
		$gareDepart = $VilleDepart;
		$gareArrive = $VilleArrivee;
		break;
	case 'r':
		$gareDepart = $VilleArrivee;
		$gareArrive = $VilleDepart; 
		break;
	default:
		$gareDepart = $VilleDepart;
		$gareArrive = $VilleArrivee;
		break;
}

 
//******************************** Date de heures d'exection *****************************/
date_default_timezone_set("Europe/Paris");
$date = date("Ymd\TH:i"); 
echo $rChariot."Date et heure d'execution: ".$date.$rChariot;

//********************************  Récupération des données *****************************/
$query = 'https://'.$api_sncf_user.'@api.sncf.com/v1/coverage/sncf/journeys?from='.$gareDepart.'&to='.$gareArrive.'&datetime='.$date.'&datetime_represents=departure&min_nb_journeys=4';
echo $rChariot.'Requete: '.$query.$rChariot.$rChariot;

$response=file_get_contents($query);
$json = json_decode($response, true);

// Boucle sur les trajets
echo $rChariot."************TRAINS*************";

foreach($json['journeys'] as $trains) // Lecture des trajets
	{  	
		$dateDepart = $trains['departure_date_time']; echo $rChariot.'Heure de depart: '.$dateDepart; // Date de départ
		$HeuredeDepart = substr($dateDepart,9,4);  // Heure de départ
		
		$dateArrive = $trains['arrival_date_time']; echo $rChariot."Heure d'arrive: ".$dateArrive; // Date d'arrivée
		$heuredarrive = substr($dateArrive,9,4);  // Heure d'arrivé
		$numtrain = $trains['sections'][1]['display_informations']['headsign']; //Numero du train
		echo $rChariot.'Numero de train: '.$numtrain;
		
		if ( $trains['status'] != "" )  // Cas du train en retard
			{ 	$retard = '';
				$text = '';
				echo $rChariot.'Statut du train: '.$trains['status'] ;
				if ($trains['status'] == 'NO_SERVICE'){ 
					echo $rChariot."************TRAINS*************";
					continue; //Affichage du statut}
				}
				$numdisrup = $trains['sections'][1]['display_informations']['links'][0]['id']; //Affichage de l'ID de retard
				echo $rChariot.'ID de retard: '.$numdisrup;
				
				//Récupération des retards
				$retards = $json['disruptions']; 
				//Recherche des retards
				foreach($retards as $retard)
				{   echo $rChariot.'ID de retard dans les retards: '.$retard['disruption_id'];
				
					if ( $retard['disruption_id']== $numdisrup )
						{ 	// Boucle sur l'ensemble des impacts du retard			
							foreach($retard['impacted_objects'][0]['impacted_stops'] as $impactStop)
								{ if ( substr($impactStop['base_departure_time'],0,4) == $HeuredeDepart )  // Recherche du lieu que l'on veut
									{   
										$updatetime = $impactStop['amended_departure_time']; // Nouvel Horaire
										echo $rChariot.'Nouvelle heure de depart: '; echo substr($updatetime,0,2);echo substr($updatetime,2,2);
										// Calcul du retard
										$retard =  ( substr($updatetime,0,2) * 60 + substr($updatetime,2,2)  ) - 
												   ( substr($HeuredeDepart,0,2) * 60 + substr($HeuredeDepart,2,2) ); //En minutes
										echo $rChariot.'Retard en mn: '.$retard; // Affichage du retard
										$text = '-'.$retard.'mn, '; 
										break;
									}
								}
						}
					
				}
			}
			else 
			{ $text = ', ';} // Train est à l'heure 
			
		    $result = $result.substr($HeuredeDepart,0,2).'h'.substr($HeuredeDepart,2,2).$text;
			echo $rChariot."************TRAINS*************";
	}
	
	$result = substr($result,0,-2);; // Supprime les deux derniers caractères
	echo $rChariot.$rChariot."Resultat: ".$result.$rChariot;  // Affichage des résultats
	
	$json_result = maj_periph($periph_id,$result); //Mise à jour Eedomus

// Fonction
function supprimer_accents($str, $encoding='ISO-8859-1')
{
    // transformer les caractères accentués en entités HTML
    $str = htmlentities($str, ENT_NOQUOTES, $encoding);
  
    // remplacer les entités HTML pour avoir juste le premier caractères non accentués
    // Exemple : "&ecute;" => "e", "&Ecute;" => "E", "Ã " => "a" ...
    $str = preg_replace('#&([A-za-z])(?:acute|grave|cedil|circ|orn|ring|slash|th|tilde|uml);#', '\1', $str);
  
    // Remplacer les ligatures tel que : Œ, Æ ...
    // Exemple "Å“" => "oe"
    $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
    // Supprimer tout le reste
    $str = preg_replace('#&[^;]+;#', '', $str);
    // Supprimer les espaces
    $str = preg_replace('/\s/', '-', $str);
  
    return $str;
}

function maj_periph( $periphID, $val )
{
	global $api_user;
	global $IPeedomus;
	global $api_secret;
	global $IPLocal;
	global $rChariot;

// Remplacement des blancs par le code hexa
	$val = str_replace(' ', '%20', $val);
// Remplacement des points virgules par le blanc
	$val = str_replace(';','%20', $val);
// Remplacement des accents
	$val = supprimer_accents($val);
	$val = utf8_encode($val);
	
//Contruction de l'url Local
	$url = "http://".$IPLocal."/set?action=periph.value"; 
	$url .= "&api_user=$api_user";
	$url .= "&api_secret=$api_secret";
	$url .= "&periph_id=$periphID";
	$url .= "&value=$val";
	
	echo $rChariot."URL locale Mise à jour Eedomus: ".$url.$rChariot;
	
// Mis à jour du périphérique
	$result = @file_get_contents($url);
	
	if (strpos($result, '"success": 1') == false)
	{
// On essaie sur le cloud	  
		$url = str_replace($IPLocal, $IPeedomus, $url);
		
		echo $rChariot."URL cloud: ".$url.$rChariot;
	  
 // Mis à jour du périphérique
		$result = file_get_contents($url);
		
		if (strpos($result, '"success": 1') == false)
		{ echo $rChariot."Une erreur est survenue lors de la mise à jour [".$result."]".$rChariot;}
	}
	return $result;
} 
/*
/usr/bin/php /var/www/eedomus/scripts/gare2.php a
*/
?>