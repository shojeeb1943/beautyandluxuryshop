<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
  <channel>
    <title>{{ getWebConfig('company_name') }}</title>
    <link>{{ url('/') }}</link>
    @foreach($products as $p)
    <item>
      <g:id>{{ $p['id'] }}</g:id>
      <g:title>{{ $p['name'] }}</g:title>
      <g:description>{{ $p['description'] }}</g:description>
      <g:link>{{ $p['link'] }}</g:link>
      <g:image_link>{{ $p['image_link'] }}</g:image_link>
      <g:availability>{{ $p['availability'] }}</g:availability>
      <g:price>{{ $p['price'] }}</g:price>
      <g:condition>new</g:condition>
      @if($p['item_group_id'])
      <g:item_group_id>{{ $p['item_group_id'] }}</g:item_group_id>
      @endif
      @if($p['sku'])
      <g:mpn>{{ $p['sku'] }}</g:mpn>
      @endif
      @if($p['brand'])
      <g:brand>{{ $p['brand'] }}</g:brand>
      @endif
      @if($p['category'])
      <g:google_product_category>{{ $p['category'] }}</g:google_product_category>
      @endif
    </item>
    @endforeach
  </channel>
</rss>
