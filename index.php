
<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/functions.php';

$landing_cache_key = 'landing_page_html:' . date('Y-m-d-H');
$landing_html = get_cached_data($landing_cache_key, 300, function() {
    ob_start();
    ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="" name="description">
    <meta content="" name="keywords">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="0HoEraXqh4F6ZFrJSfDeStsJrgkt40dU0ZWAeVpE">
    <meta name="csrf_token" value="0HoEraXqh4F6ZFrJSfDeStsJrgkt40dU0ZWAeVpE" />

    <!-- Favicons -->
    <link href="https://santarosacity.gov.ph/assets/favicon.png" rel="apple-touch-icon">
    <link href="https://santarosacity.gov.ph/assets/favicon.png" rel="icon">

    <title>Santa Rosa</title>

    <!-- Favicon -->
    <link href="https://santarosacity.gov.ph/assets/img/logo-footer.png" rel="icon">
    
    <!-- Nexa Fonts -->
    <link href="https://santarosacity.gov.ph/assets/fonts/stylesheet.css" rel="stylesheet">


    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="https://santarosacity.gov.ph/assets/lib/animate/animate.min.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/assets/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/assets/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="https://santarosacity.gov.ph/assets/css/bootstrap.css" rel="stylesheet">
    

    <!-- Template Stylesheet -->
    <link href="https://santarosacity.gov.ph/assets/css/style.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/index.css">
    <style >[wire\:loading], [wire\:loading\.delay], [wire\:loading\.inline-block], [wire\:loading\.inline], [wire\:loading\.block], [wire\:loading\.flex], [wire\:loading\.table], [wire\:loading\.grid], [wire\:loading\.inline-flex] {display: none;}[wire\:loading\.delay\.shortest], [wire\:loading\.delay\.shorter], [wire\:loading\.delay\.short], [wire\:loading\.delay\.long], [wire\:loading\.delay\.longer], [wire\:loading\.delay\.longest] {display:none;}[wire\:offline] {display: none;}[wire\:dirty]:not(textarea):not(input):not(select) {display: none;}input:-webkit-autofill, select:-webkit-autofill, textarea:-webkit-autofill {animation-duration: 50000s;animation-name: livewireautofill;}@keyframes livewireautofill { from {} }</style>
    <style>
        body{ background: white !important;}
        /* Ensure the Nexa site font applies to this page and support template classes */
        body, h1, h2, h3, h4, h5, h6, p, a, span, div {
            font-family: 'Nexa', 'Inter', sans-serif !important;
        }
        .nexabook { font-family: 'Nexa', 'Inter', sans-serif !important; font-weight: 400; }
        .nexabold { font-family: 'Nexa', 'Inter', sans-serif !important; font-weight: 600; }
        .nexablack { font-family: 'Nexa', 'Inter', sans-serif !important; font-weight: 800; }
    </style>
    <style>
        /* Prevent horizontal swipe-gap on mobile caused by overflow */
        html, body { overflow-x: clip !important; max-width: 100vw !important; }
        /* Disable background scrolling while Bootstrap modal is open */
        body.modal-open, html.modal-open { overflow: hidden !important; }
    </style>
    
</head>

<body>
    <!-- Messenger Chat Plugin Code -->
    <div id="fb-root"></div>

    <!-- Your Chat Plugin code -->
    <div id="fb-customer-chat" class="fb-customerchat">
    </div>

    <div id="app">

        <!-- Spinner Start -->
                <!-- Spinner End -->


        <!-- Navbar Start -->
        <nav class="navbar bg-theme navbar-light shadow sticky-top p-0">
            <a href="./" class="navbar-brand d-flex align-items-center px-4 px-lg-5 mx-auto">
                <img class="h-95" src="https://santarosacity.gov.ph/assets/img/logo-header.png">
            </a>
            <div class="position-absolute end-0">
                <button type="button" class="navbar-toggler me-4" data-bs-toggle="offcanvas"
                    data-bs-target="#offcanvasExample">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
        </nav>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasRightLabel"
            data-bs-backdrop="false" data-bs-scroll="true">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title text-theme nexabook" id="offcanvasExampleLabel">Navigation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body bg-white navbar nav-tabs flex-grow-0">
                <div class="navbar-collapse collapse show" id="navbarCollapse" style="">
                    <div class="navbar-nav ms-auto p-4 p-lg-0 nexabold">
                        <a href="https://santarosacity.gov.ph/welcome" class="nav-item nav-link">Home</a>
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">About Us</a>
                            <div class="dropdown-menu fade-up m-0">
                                <a href="https://santarosacity.gov.ph/about-us#historical" class="dropdown-item">Historical
                                    Background</a>
                                <a href="https://santarosacity.gov.ph/about-us#barangays" class="dropdown-item">Barangays</a>
                                <a href="https://santarosacity.gov.ph/about-us#departments" class="dropdown-item">Departments</a>
                            </div>
                        </div>
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">Government</a>
                            <div class="dropdown-menu fade-up m-0">
                                <a href="https://santarosacity.gov.ph/government-officials" class="dropdown-item">Government
                                    Officials</a>
                                <a href="https://santarosacity.gov.ph/governance-model" class="dropdown-item">Governance Model</a>
                            </div>
                        </div>

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">Transparency</a>
                            <div class="dropdown-menu fade-up m-0">
                                
                                
                                
                                
                                <a href="https://santarosacity.gov.ph/city-ordinances" class="dropdown-item">City Ordinances</a>
                                <a href="https://santarosacity.gov.ph/resolutions" class="dropdown-item">Resolutions</a>
                            </div>
                        </div>



                        <a href="https://santarosacity.gov.ph/online-forms" class="nav-item nav-link">Online Forms</a>
                        <a href="https://santarosacity.gov.ph/bid-opportunities" class="nav-item nav-link">Bid Opportunities</a>
                        <a href="https://santarosacity.gov.ph/e-services" class="nav-item nav-link">eServices</a>

                        <a href="erequest-mo" class="nav-item nav-link">eRequest Mo</a>
                        <a href="https://santarosacity.gov.ph/gallery" class="nav-item nav-link">City Gallery</a>
                        <a href="https://santarosacitysp.com/" target="_blank" class="nav-item nav-link">Sangguniang
                            Panlungsod</a>

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">Contact Us</a>
                            <div class="dropdown-menu fade-up m-0">
                                <a href="https://santarosacity.gov.ph/city-directory" class="dropdown-item">City Directory</a>
                                <a href="https://santarosacity.gov.ph/contact-us" class="dropdown-item">Send Us A Message</a>
                            </div>
                        </div>
                        <a href="https://santarosacity.gov.ph/faqs" class="nav-item nav-link">FAQs</a>
                    </div>
                    
                </div>
            </div>
        </div>
        <!-- Navbar End -->

        <!-- Page Header Start -->
        <div class="container-fluid page-header mb-4 p-0 px-2 pb-0" style="background-image: url(https://santarosacity.gov.ph/assets/img/img1.png);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container">
            <div class="col-lg-5 col-md-7">
                <p class="nexabook display-6 fw-lighter text-white mb-0 animated slideInDown">WELCOME TO THE</p>
                <p class="nexablack display-6 text-white mb-3 animated slideInDown">CITY OF SANTA ROSA</p>
                <p class="text-white mb-3 animated slideInDown text-transform-unset">The city is driven by transparent and accountable local
                    <strong>governance, effective civil servants and empowered citizenry</strong></p>
            </div>
        </div>
    </div>
</div>
        <!-- Page Header End -->

        <div class="pb-0">
                <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
        <span class="sr-only">Loading...</span>
    </div>
</div>
    <!-- Navs Start -->
    <div class="container-xxl py-5 pt-0">
        <div class="container">
            <div class="row">
                <div class="col-xl-3 col-lg-4 col-md-4 py-3">
                    <a href="https://santarosacity.gov.ph/about-us" class="h-100 btn btn-outline-primary rounded-3 nexabold d-flex py-4">
                        <div class="row g-0 mx-auto">
                            <div class="col-md-3 pe-1 my-auto">
                                <img src="https://santarosacity.gov.ph/assets/img/icn-about_us.png" class="img-fluid rounded-3" alt="...">
                            </div>
                            <div class="col-md-8 px-2 my-auto text-capitalize text-lg-start text-sm-center">About Us</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-4 py-3">
                    <a href="erequest-mo" class="h-100 btn btn-outline-primary rounded-3 nexabold d-flex py-4">
                        <div class="row g-0 mx-auto">
                            <div class="col-md-4 pe-1 my-auto">
                                <img src="https://santarosacity.gov.ph/assets/img/icn-eServices.png" class="img-fluid rounded-3" alt="...">
                            </div>
                            <div class="col-md-8 px-2 my-auto text-lg-start text-sm-center text-initial">eRequest Mo</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-4 py-3">
                    <a href="https://santarosacity.gov.ph/transparency?type=full-disclosure" class="h-100 btn btn-outline-primary rounded-3 nexabold d-flex py-4">
                        <div class="row g-0 mx-auto">
                            <div class="col-md-3 pe-1 my-auto">
                                <img src="https://santarosacity.gov.ph/assets/img/icn-government_officials.png" class="img-fluid rounded-3" alt="...">
                            </div>
                            <div class="col-md-9 px-2 my-auto text-capitalize text-lg-start text-sm-center">Full Disclosure</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-4 py-3">
                    <a href="https://santarosacity.gov.ph/transparency?type=citizens-charter" class="h-100 btn btn-outline-primary rounded-3 nexabold d-flex py-4">
                        <div class="row g-0 mx-auto">
                            <div class="col-md-3 pe-1 my-auto">
                                <img src="https://santarosacity.gov.ph/assets/img/icn-government_officials.png" class="img-fluid rounded-3" alt="...">
                            </div>
                            <div class="col-md-9 px-2 my-auto text-capitalize text-lg-start text-sm-center">Citizen's Charter</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-4 py-3">
                    <a href="https://santarosacity.gov.ph/e-services" class="h-100 btn btn-outline-primary rounded-3 nexabold d-flex py-4">
                        <div class="row g-0 mx-auto">
                            <div class="col-md-4 pe-1 my-auto">
                                <img src="https://santarosacity.gov.ph/assets/img/icn-eServices.png" class="img-fluid rounded-3" alt="...">
                            </div>
                            <div class="col-md-8 px-2 my-auto text-lg-start text-sm-center text-initial">eServices</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-4 py-3">
                    <a href="https://santarosacity.gov.ph/online-forms" class="h-100 btn btn-outline-primary rounded-3 nexabold d-flex py-4">
                        <div class="row g-0 mx-auto">
                            <div class="col-md-3 pe-1 my-auto">
                                <img src="https://santarosacity.gov.ph/assets/img/icn-online_forms.png" class="img-fluid rounded-3" alt="...">
                            </div>
                            <div class="col-md-9 px-2 my-auto text-capitalize text-lg-start text-sm-center">Online Forms</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-4 py-3">
                    <a href="https://santarosacity.gov.ph/online-payments" class="h-100 btn btn-outline-primary rounded-3 nexabold d-flex py-4">
                        <div class="row g-0 mx-auto">
                            <div class="col-md-3 pe-1 my-auto">
                                <img src="https://santarosacity.gov.ph/assets/img/icn-online_payments.png" class="img-fluid rounded-3" alt="...">
                            </div>
                            <div class="col-md-9 px-2 my-auto text-capitalize text-lg-start text-sm-center">Online Payments</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-4 py-3">
                    <a href="https://santarosacity.gov.ph/bid-opportunities" class="h-100 btn btn-outline-primary rounded-3 nexabold d-flex py-4">
                        <div class="row g-0 mx-auto">
                            <div class="col-md-3 pe-1 my-auto">
                                <img src="https://santarosacity.gov.ph/assets/img/icn-bid_opportunities.png" class="img-fluid rounded-3" alt="...">
                            </div>
                            <div class="col-md-9 px-2 my-auto text-capitalize text-lg-start text-sm-center">Bid Opportunities</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Navs End -->



    <!-- Small Banner Start -->
    <div class="container-fluid page-header p-0 px-2 pb-0" style="background-image: url(https://santarosacity.gov.ph/assets/img/img2.png);">
        <div class="container-fluid page-header-inner py-5">
            <div class="container">
                <div class="col-lg-5 col-md-7">
                    <p class="text-white mb-0 animated slideInDown text-transform-unset">Santa Rosa city is making effort to ensure that people
                        <strong>have access to our services and information.</strong></p>
                </div>
            </div>
        </div>
    </div>
    <!-- Small Banner End -->


    <!-- Body Start -->
    <div class="container-xxl py-5">
        <div class="container">
            <div class="row g-4 mx-auto justify-content-center pb-3">
                

                <!-- Announcements Start -->
                <div class="col-lg-6 col-md-12 wow fadeInUp" data-wow-delay="0.3s">
                    <div class="team-item">
                        <div class="card rounded-5 shadow">
                            <div class="card-body mx-auto">
                              <h5 class="card-title text-center text-theme">News & Announcements</h5>
                                <div id="carouselExampleControls" style="height: 507px" class="carousel slide" data-bs-ride="carousel">
                                    <div class="carousel-inner">
                                                                                                                                                                            <div class="carousel-item active">
                                                    <p class="card-text text-center">
                                                                                                                    NEW LOCATION OF THE NEGOSYO CENTER
                                                                                                            </p>
                                                    <a href="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/1e0ed332451dcbc0171cf15b1a630bde.jpg" class="lightbox-toggle" data-gallery="test-gallery"
                                                        
                                                        data-caption="NEW LOCATION OF THE NEGOSYO CENTER"
                                                        >
                                                        <img src="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/1e0ed332451dcbc0171cf15b1a630bde.jpg" class="img-fluid d-block mx-auto" style="" alt="...">
                                                    </a>
                                                </div>
                                                                                                                                        <div class="carousel-item ">
                                                    <p class="card-text text-center">
                                                                                                                    𝐏𝐀𝐁𝐀𝐓𝐈𝐃 𝐒𝐀 𝐏𝐔𝐁𝐋𝐈𝐊𝐎
                                                                                                            </p>
                                                    <a href="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/f0970bba8b9ad56c84f2c393fda88326.jpg" class="lightbox-toggle" data-gallery="test-gallery"
                                                        
                                                        data-caption="𝐏𝐀𝐁𝐀𝐓𝐈𝐃 𝐒𝐀 𝐏𝐔𝐁𝐋𝐈𝐊𝐎"
                                                        >
                                                        <img src="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/f0970bba8b9ad56c84f2c393fda88326.jpg" class="img-fluid d-block mx-auto" style="" alt="...">
                                                    </a>
                                                </div>
                                                                                                                                        <div class="carousel-item ">
                                                    <p class="card-text text-center">
                                                                                                                    𝗚𝗖𝗮𝘀𝗵 𝗗𝗶𝗴𝗶𝘁𝗮𝗹 𝗘𝘅𝗰𝗲𝗹𝗹𝗲𝗻𝗰𝗲 𝗔𝘄𝗮𝗿𝗱𝘀
                                                                                                            </p>
                                                    <a href="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/30250f3e40ff23ab52c6a6b6c512309f.jpg" class="lightbox-toggle" data-gallery="test-gallery"
                                                        
                                                        data-caption="𝗚𝗖𝗮𝘀𝗵 𝗗𝗶𝗴𝗶𝘁𝗮𝗹 𝗘𝘅𝗰𝗲𝗹𝗹𝗲𝗻𝗰𝗲 𝗔𝘄𝗮𝗿𝗱𝘀"
                                                        >
                                                        <img src="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/30250f3e40ff23ab52c6a6b6c512309f.jpg" class="img-fluid d-block mx-auto" style="" alt="...">
                                                    </a>
                                                </div>
                                                                                                                                        <div class="carousel-item ">
                                                    <p class="card-text text-center">
                                                                                                                    Outstanding Local Civil Registry Office (2nd Place) sa Provincial Level
                                                                                                            </p>
                                                    <a href="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/ac399857259a70219d4fcd3e23540ae8.jpg" class="lightbox-toggle" data-gallery="test-gallery"
                                                        
                                                        data-caption="Outstanding Local Civil Registry Office (2nd Place) sa Provincial Level"
                                                        >
                                                        <img src="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/ac399857259a70219d4fcd3e23540ae8.jpg" class="img-fluid d-block mx-auto" style="" alt="...">
                                                    </a>
                                                </div>
                                                                                                                                        <div class="carousel-item ">
                                                    <p class="card-text text-center">
                                                                                                                    LIBRENG ANTI-RABIES MASS VACCINATION para sa buwan ng MAYO 2026
                                                                                                            </p>
                                                    <a href="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/23a45444794db5e3ddb9667bad3b9d6f.jpg" class="lightbox-toggle" data-gallery="test-gallery"
                                                        
                                                        data-caption="LIBRENG ANTI-RABIES MASS VACCINATION para sa buwan ng MAYO 2026"
                                                        >
                                                        <img src="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/23a45444794db5e3ddb9667bad3b9d6f.jpg" class="img-fluid d-block mx-auto" style="" alt="...">
                                                    </a>
                                                </div>
                                                                                                                                        <div class="carousel-item ">
                                                    <p class="card-text text-center">
                                                                                                                    𝐅𝐫𝐞𝐞 𝐁𝐨𝐨𝐤 𝐚𝐧𝐝 𝐑𝐢𝐝𝐞 𝐒𝐡𝐮𝐭𝐭𝐥𝐞
                                                                                                            </p>
                                                    <a href="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/424acb3a99aa67d6e8626f4467a46916.jpg" class="lightbox-toggle" data-gallery="test-gallery"
                                                        
                                                        data-caption="𝐅𝐫𝐞𝐞 𝐁𝐨𝐨𝐤 𝐚𝐧𝐝 𝐑𝐢𝐝𝐞 𝐒𝐡𝐮𝐭𝐭𝐥𝐞"
                                                        >
                                                        <img src="https://sta-rosa-s3.s3.ap-southeast-1.amazonaws.com/announcements/424acb3a99aa67d6e8626f4467a46916.jpg" class="img-fluid d-block mx-auto" style="" alt="...">
                                                    </a>
                                                </div>
                                                                                                                                                                        </div>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleControls" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Previous</span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleControls" data-bs-slide="next">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Next</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Announcements End -->

                <div class="col-lg-6 col-md-12 wow fadeInUp" data-wow-delay="0.5s">
                    <div class="team-item">
                        <div class="card rounded-5 shadow box">
                            <div class="card-body mx-auto">
                              <h5 class="card-title text-center text-theme">Facebook Feeds</h5>
                                <iframe src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fsantarosacitygovernment&tabs=timeline&width=340&height=500&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&appId=115807028164433"
                                    
                                    style="border:none;overflow:hidden; width:340px; height: 500px" scrolling="no"
                                    frameborder="0" allowfullscreen="true"
                                    allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
                                <!-- iframe id="feeds" src=""
                                
                                style="border:none;overflow:hidden; width:340px; height: 500px" scrolling="no"
                                frameborder="0" allowfullscreen="true"
                                allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe-->

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
                        <div class="row g-4 pt-3">
                        
                
                <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="0.7s">
                    <div class="team-item shadow bg-light h-100">
                        <div class="position-relative overflow-hidden">
                            <img class="img-fluid" src="https://i3.ytimg.com/vi/d86Dr9B2hA4/hqdefault.jpg" alt="">
                            <div class="team-overlay position-absolute start-0 top-0 w-100 h-100">
                                <a class="btn btn-square mx-1  fs-1 h-25 w-25 rounded-5 stretched-link"
                                    
                                    href="https://youtu.be/d86Dr9B2hA4"
                                    data-toggle="lightbox"
                                    data-caption="BPLO Payment Guide"
                                    
                                    
                                    
                                    ><i class="fab fa-youtube"></i></a>
                            </div>
                        </div>
                        <div class="bg-light text-center p-4">
                            <h6 class="nexabook mb-0 text-theme">BPLO Payment Guide</h6>
                            
                        </div>
                    </div>
                </div>
            
                
                <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="0.7s">
                    <div class="team-item shadow bg-light h-100">
                        <div class="position-relative overflow-hidden">
                            <img class="img-fluid" src="https://i3.ytimg.com/vi/ZS1Ei-vr09I/hqdefault.jpg" alt="">
                            <div class="team-overlay position-absolute start-0 top-0 w-100 h-100">
                                <a class="btn btn-square mx-1  fs-1 h-25 w-25 rounded-5 stretched-link"
                                    
                                    href="https://youtu.be/ZS1Ei-vr09I"
                                    data-toggle="lightbox"
                                    data-caption="RPT Payment Guide"
                                    
                                    
                                    
                                    ><i class="fab fa-youtube"></i></a>
                            </div>
                        </div>
                        <div class="bg-light text-center p-4">
                            <h6 class="nexabook mb-0 text-theme">RPT Payment Guide</h6>
                            
                        </div>
                    </div>
                </div>
            
                
                <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="0.7s">
                    <div class="team-item shadow bg-light h-100">
                        <div class="position-relative overflow-hidden">
                            <img class="img-fluid" src="https://i3.ytimg.com/vi/bRoLWYQUFXk/hqdefault.jpg" alt="">
                            <div class="team-overlay position-absolute start-0 top-0 w-100 h-100">
                                <a class="btn btn-square mx-1  fs-1 h-25 w-25 rounded-5 stretched-link"
                                    
                                    href="https://youtu.be/bRoLWYQUFXk"
                                    data-toggle="lightbox"
                                    data-caption="OBO Payment Guide"
                                    
                                    
                                    
                                    ><i class="fab fa-youtube"></i></a>
                            </div>
                        </div>
                        <div class="bg-light text-center p-4">
                            <h6 class="nexabook mb-0 text-theme">OBO Payment Guide</h6>
                            
                        </div>
                    </div>
                </div>
            
                
                <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="0.7s">
                    <div class="team-item shadow bg-light h-100">
                        <div class="position-relative overflow-hidden">
                            <img class="img-fluid" src="https://i3.ytimg.com/vi/xR3Nj0Uxl54/hqdefault.jpg" alt="">
                            <div class="team-overlay position-absolute start-0 top-0 w-100 h-100">
                                <a class="btn btn-square mx-1  fs-1 h-25 w-25 rounded-5 stretched-link"
                                    
                                    href="https://youtu.be/xR3Nj0Uxl54"
                                    data-toggle="lightbox"
                                    data-caption="Oath-Taking Ceremony ng Newly-Elected Officials"
                                    
                                    
                                    
                                    ><i class="fab fa-youtube"></i></a>
                            </div>
                        </div>
                        <div class="bg-light text-center p-4">
                            <h6 class="nexabook mb-0 text-theme">Oath-Taking Ceremony ng Newly-Elected Officials</h6>
                            
                        </div>
                    </div>
                </div>
                                    </div>
            
            <div class="row g-4 justify-content-center pb-3 mt-4">
                <div class="col-lg-6 text-center wow fadeInUp" data-wow-delay="0.7s">
                    <a href="https://santarosacity.gov.ph/gallery" class="btn btn-primary text-transform-unset">Go to Gallery</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Body End -->


        </div>

        <!-- Footer Start -->
        <footer id="footer" class="footer pt-0 pb-0">
            <div class="container-fluid bg-theme text-light footer pt-2 mt-0 wow fadeIn" data-wow-delay="0.1s">
                <div class="container py-5">
                    <div class="row g-5">
                        <div class="col-lg-2 col-md-3">
                            <div class="d-lg-block d-none">
                                <img class="img img-fluid w-100" src="https://santarosacity.gov.ph/assets/img/logo-footer.png">
                            </div>
                            <div class="d-lg-none d-md-none d-sm-block">
                                <img class="img img-fluid w-50" src="https://santarosacity.gov.ph/assets/img/logo-footer.png">
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <p class="text-light mb-3 fs-3">Contact Us</p>
                            <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>J. P Rizal, City Government Center,
                                City of Santa Rosa, Laguna, Philippines, 4026</p>
                            <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>Local Number: (049)530-0015(Local 0)</p>
                            <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>Manila Line: (02) 8519-4024</p>
                            <p class="text-light mb-3 mt-4 fs-3">Follow Us</p>
                            <div class="d-flex pt-0">
                                <a class="btn btn-outline-light btn-social p-4"
                                    href="https://www.facebook.com/citygovernmentofsantarosa" target="_blank"><i
                                        class="fab fa-facebook-f"></i></a>
                                <a class="btn btn-outline-light btn-social p-4" href="https://twitter.com/santarosacity"
                                    target="_blank"><i class="fab fa-twitter"></i></a>
                                <a class="btn btn-outline-light btn-social p-4"
                                    href="https://www.youtube.com/user/SantaRosaPhils" target="_blank"><i
                                        class="fab fa-youtube"></i></a>
                                <a class="btn btn-outline-light btn-social p-4"
                                    href="https://www.instagram.com/cityofsantarosalaguna/" target="_blank"><i
                                        class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <p class="text-light mb-3 fs-3">Quick Links</p>
                            <a class="btn btn-link" href="https://santarosacity.gov.ph/welcome">Home</a>
                            <a class="btn btn-link" href="https://santarosacity.gov.ph/government-officials#citymayor">City Mayor</a>
                            <a class="btn btn-link request-footer-brand-link" href="erequest-mo">eRequest Mo</a>
                            <a class="btn btn-link" href="https://onesantarosa.com/" target="_blank">News</a>
                            <a class="btn btn-link" href="https://www.santarosacity.ph/SCVT/"
                                target="_blank">Vaccinations</a>
                            <a class="btn btn-link" href="https://santarosacity.gov.ph/about-us#departments">Departments</a>
                            <a class="btn btn-link" href="terms">eRequest Mo Terms and Conditions</a>
                            <a class="btn btn-link" href="privacy">eRequest Mo Privacy Policy</a>
                            <a class="btn btn-link" href="cookies">eRequest Mo Cookie Policy</a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <p class="text-light mb-3 fs-3">Government Links</p>
                            <a class="btn btn-link" target="_blank" href="https://op-proper.gov.ph/">Office of the
                                President</a>
                            <a class="btn btn-link" target="_blank" href="https://ovp.gov.ph/">Office of the Vice
                                President</a>
                            <a class="btn btn-link" target="_blank" href="http://legacy.senate.gov.ph/">Senate of the
                                Philippines</a>
                            <a class="btn btn-link" target="_blank" href="https://www.congress.gov.ph/">House of the
                                Representatives</a>
                            <a class="btn btn-link" target="_blank" href="https://sc.judiciary.gov.ph/">Supreme
                                Court</a>
                            <a class="btn btn-link" target="_blank" href="http://ca.judiciary.gov.ph/">Court of
                                Appeals</a>
                            <a class="btn btn-link" target="_blank"
                                href="https://sb.judiciary.gov.ph/">Sandiganbayan</a>
                        </div>
                        
                    </div>
                </div>
            </div>
        </footer><!-- End Footer -->
        <!-- Footer End -->

        <!-- Back to Top -->
        <a href="#" class="btn btn-lg btn-warning btn-lg-square text-white rounded-circle back-to-top" aria-label="Back to top">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M6 15l6-6 6 6" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M6 11l6-6 6 6" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>

    </div>


    <!-- JavaScript Libraries -->
    
    <!-- <script src="/js/app.js"></script> -->

    <script src="https://santarosacity.gov.ph/js/jquery-3.4.1.js"></script>
    
    
    <script src="https://santarosacity.gov.ph/js/bootstrap.bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    
    <script src="https://santarosacity.gov.ph/assets/lib/wow/wow.min.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/easing/easing.min.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/waypoints/waypoints.min.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/counterup/counterup.min.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/owlcarousel/owl.carousel.min.js"></script>

    <!-- Template Javascript -->
    <script src="https://santarosacity.gov.ph/assets/js/main.js"></script>
    <script>
    // Sticky Navbar Directional Transition
    $(window).off('scroll');
    var lastScrollTop = 0;
    $(window).scroll(function () {
        var st = $(this).scrollTop();
        
        // Navbar direction logic
        if (st > 100) {
            if (st > lastScrollTop) {
                // Scrolling down
                $('.sticky-top').css('top', '-100px');
            } else {
                // Scrolling up
                $('.sticky-top').css('top', '0px');
            }
        } else {
            // At the very top of the page
            $('.sticky-top').css('top', '0px');
        }
        
        lastScrollTop = st;

        // Back-to-top button
        if (st > 300) {
            $('.back-to-top').fadeIn('slow');
        } else {
            $('.back-to-top').fadeOut('slow');
        }
    });
    </script>

    <!-- Messenger Chat Plugin Start -->

    <script>
        var chatbox = document.getElementById('fb-customer-chat');
        chatbox.setAttribute("page_id", "141286865889654");
        chatbox.setAttribute("attribution", "biz_inbox");
    </script>

    <script>
        $(function () {
            $('[data-toggle=tooltip]').tooltip();
        });
    </script>

    <!-- Your SDK code -->
    <script>
        window.fbAsyncInit = function () {
            FB.init({
                xfbml: true,
                version: 'v15.0'
            });
        };

        (function (d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s); js.id = id;
            js.src = 'https://connect.facebook.net/en_US/sdk/xfbml.customerchat.js';
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));
    </script>

    <!-- Messenger Chat Plugin End -->

    <script defer src="https://santarosacity.gov.ph/js/alpine.min.js" defer></script>
    <script src="https://santarosacity.gov.ph/vendor/livewire/livewire.js?id=90730a3b0e7144480175" data-turbo-eval="false" data-turbolinks-eval="false" ></script><script data-turbo-eval="false" data-turbolinks-eval="false" >window.livewire = new Livewire();window.Livewire = window.livewire;window.livewire_app_url = 'https://santarosacity.gov.ph';window.livewire_token = '0HoEraXqh4F6ZFrJSfDeStsJrgkt40dU0ZWAeVpE';window.deferLoadingAlpine = function (callback) {window.addEventListener('livewire:load', function () {callback();});};let started = false;window.addEventListener('alpine:initializing', function () {if (! started) {window.livewire.start();started = true;}});document.addEventListener("DOMContentLoaded", function () {if (! started) {window.livewire.start();started = true;}});</script>
    
    <script src="https://cdn.jsdelivr.net/npm/bs5-lightbox@1.8.2/dist/index.bundle.min.js"></script>
    <script>

    $(document).ready(function(){
        let width = $(".box").width()-30;
        if(width >= 500) width = 500;
        let url = "https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fsantarosacityphofficial&tabs=timeline&width="+width+"&height=500&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&appId=115807028164433"
        $("#feeds").css('width', width).attr('src', url);
        // console.log(width);
    });

    $(document).ready(function() {

        const options = {
            keyboard: true,
            size: 'xl'
        };

        document.querySelectorAll('.lightbox-toggle').forEach((el) => el.addEventListener('click', (e) => {
            e.preventDefault();
            const lightbox = new Lightbox(el, options);
            lightbox.show();
            $(".lightbox-carousel").removeAttr('data-bs-interval');
        }));

    // document ready
    });
    </script>

    <!-- Capstone Notice Modal -->
    <div class="modal fade" id="capstoneNoticeModal" tabindex="-1" aria-labelledby="capstoneNoticeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="capstoneNoticeModalLabel">Important Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">This system is a capstone project and is intended for academic demonstration only. It is not an official Santa Rosa government transaction portal.</p>
                    <p class="mb-3">Please do not use this site for real government requests, personal records, or official legal transactions. All information submitted here is for demonstration purposes only.</p>
                    <p class="mb-3">If you need more information or have any questions about this project, you may contact me directly.</p>
                    <p class="mb-0"><strong>For actual City of Santa Rosa services, please use official government channels and verified websites.</strong></p>
                </div>
                <div class="modal-footer d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                    <div class="text-muted small">Developed by: <a href="https://loadssss.vercel.app/" target="_blank" rel="noopener noreferrer">Loadssss</a></div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    var capstoneModalEl = document.getElementById('capstoneNoticeModal');

    // <html> is the actual scrolling root of the page, not <body> — but
    // Bootstrap only adds "modal-open" to <body>. The CSS already has a
    // html.modal-open rule waiting, so mirror the class onto <html> too.
    capstoneModalEl.addEventListener('show.bs.modal', function () {
        document.documentElement.classList.add('modal-open');
    });
    capstoneModalEl.addEventListener('hidden.bs.modal', function () {
        document.documentElement.classList.remove('modal-open');
    });

    var capstoneModal = new bootstrap.Modal(capstoneModalEl);
    capstoneModal.show();
</script>

</body>

<?php
    return ob_get_clean();
});

echo $landing_html;
?>
</html>