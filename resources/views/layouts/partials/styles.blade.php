<head>
	<title>Digifood – Kezelőfelület</title>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="robots" content="noindex, nofollow">
	<meta name="author" content="Digifood">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="Digifood 2.0 – Kréta étkezési modulhoz csatlakozó iskolai és óvodai menzakezelő rendszer, superadmin felület.">
	<meta name="keywords" content="Kréta étkeztetési modul, menzakezelő rendszer, iskolai étkeztetés, óvodai étkeztetés">
	<meta property="og:type" content="website">
	<meta property="og:title" content="Digifood – Kréta étkezési modulhoz csatlakozó menzakezelő rendszer">
	<meta property="og:description" content="Iskolai és óvodai menzakezelő rendszer, amely a Kréta étkezési modulját egészíti ki.">
	<meta property="og:image" content="{{ asset('images/logo.png') }}">
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="Digifood – Kréta étkezési modul menzakezelő kiegészítő">
	<meta name="twitter:description" content="Kréta étkezési modulhoz csatlakozó menzakezelő rendszer intézményeknek és konyháknak.">
	<meta name="twitter:image" content="{{ asset('images/logo.png') }}">
	<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('home/favicon.png') }}">
	<link rel="stylesheet" href="{{ asset('dashboard/vendor/chartist/css/chartist.min.css') }}">
	<link href="{{ asset('dashboard/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">
	<link href="{{ asset('dashboard/css/style.css') }}" rel="stylesheet">
	<link href="{{ asset('css/admin-components.css') }}?v={{ file_exists(public_path('css/admin-components.css')) ? filemtime(public_path('css/admin-components.css')) : 1 }}" rel="stylesheet">
</head>
