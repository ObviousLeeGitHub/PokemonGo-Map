<?php 
require __DIR__ . '/vendor/autoload.php';

$curl = curl_init();
$slackclient = new Maknz\Slack\Client('https://hooks.slack.com/services/****');
$server = '10.10.10.10';

curl_setopt_array($curl, array(
        CURLOPT_PORT => '2020',
        CURLOPT_URL => 'http://' . $server . '/next_loc?lat=' . $_REQUEST['lat'] .'&lon=' . $_REQUEST['lon'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'accept: application/hal+json',
            'cache-control: no-cache',
        ),
    )
);

$response = curl_exec($curl);
$err = curl_error($curl);

$raw_data = json_decode(file_get_contents('http://' . $server . '/raw_data'), true);
$poke_data = getPokedata();

foreach ( $raw_data['pokemons'] as $pokemon ) {
    if ( (int) $poke_data[ $pokemon['pokemon_id'] ]['rarity'] > 1 ) {

        $cache = '/var/www/pogo/www/cache/' . md5( $pokemon['pokemon_id'] . $pokemon['encounter_id'] . $pokemon['spawnpoint_id'] . $pokemon['disappear_time']) . '.md5';

        if ( !file_exists($cache) ) {
            $now = (int) (time() . round(microtime() * 1000));
            $_pokemon_name = '<http://pogobase.net/' . $pokemon['pokemon_id'] . '|' . $pokemon['pokemon_name']  . '>';
            $_pokemon_distance = distance($_REQUEST['lat'], $_REQUEST['lon'], $pokemon['latitude'], $pokemon['longitude']) . 'm';
            $_time = round ( abs( ($now - $pokemon['disappear_time']) / 1000 / 60 ), 0 );
            $_pokemon_time = ( (int) $_time > 100 ? 'noen få ' : $_time ) . 'min';
            $_pokemon_map = '<https://www.google.no/maps/dir/' . $_REQUEST['lat'] . ',' . $_REQUEST['lon'] . '/' . $pokemon['latitude'] . ',' . $pokemon['longitude'] . '/?dirflg=w|fange den>! :world_map:';
            
            $slackclient->to('#p')->send( $_pokemon_name . ' er ' . $_pokemon_distance . ' unna. Du har ' . $_pokemon_time . ' til å ' . $_pokemon_map);
            file_put_contents($cache, $pokemon['encounter_id']);
        }
    }
}

function getPokedata() {
    foreach (array_map('str_getcsv', file('pokedata.csv')) as $line) {
        $pokedata[(int)$line[0]]['name'] = $line[1];
        $pokedata[(int)$line[0]]['rarity'] = (int) $line[2];
    }
    return $pokedata;
}

function distance($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return round($miles * 1.609344 * 1000);
}
