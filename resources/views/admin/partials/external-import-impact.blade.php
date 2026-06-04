@php
    $domain = (string) ($domain ?? '');
    $cityId = isset($cityId) ? (int) $cityId : null;
@endphp

<x-admin.import-hub.impact :domain="$domain" :city-id="$cityId" />
