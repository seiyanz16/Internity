@php
use Carbon\Carbon;

$currentDate = Carbon::now();
$currentYear = $currentDate->year;

if ($currentDate->month >= 6 && $currentDate->month <= 12) {
    $defaultAcademicYear=$currentYear . '-' . ($currentYear + 1);
    } else {
    $defaultAcademicYear=($currentYear - 1) . '-' . $currentYear;
    }
    @endphp
    <div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <!-- Table Function -->
                <div class="header-function d-flex flex-wrap align-items-center justify-content-between">
                    <!-- Add Data Start -->
                    <div class="d-flex flex-wrap mb-3 mb-sm-0">
                        @if ($routeCreate)
                        <a href="{{ $routeCreate }}" class="btn bg-gradient-info mb-2 mb-sm-0 me-2">
                            TAMBAH DATA
                        </a>
                        @endif

                        @if (auth()->user()->name === 'Superadmin' && request()->is('students'))
                        <a href="{{ route('students.export', request()->query()) }}" class="btn bg-gradient-success mb-2 mb-sm-0 me-2">
                            CETAK DATA
                        </a>
                        @endif

                        @if (request()->is('presences'))
                        <a href="{{ route('presences.bulkApprove', request()->query()) }}" class="btn bg-gradient-success mb-2 mb-sm-0 me-2" id="approve-all-presences">
                            APPROVE ALL
                        </a>
                        @endif

                        @if (auth()->user()->name === 'Superadmin' && request()->is('presences'))
                        <a href="{{ route('presences.export', request()->query()) }}" class="btn bg-gradient-info mb-2 mb-sm-0 me-2">
                            CETAK DATA
                        </a>
                        @endif

                        @if (request()->is('journals'))
                        <a href="{{ route('journals.bulkApprove', request()->query()) }}" class="btn bg-gradient-success mb-2 mb-sm-0 me-2" id="approve-all-journals">
                            APPROVE ALL
                        </a>
                        @endif


                        @if ($routeCreate && request()->is('scores'))
                        @php
                        $certificate = \App\Models\Certificate::where('user_id', decrypt(request()->query('user')))
                        ->where('company_id', decrypt(request()->query('company')))
                        ->first();
                        $hasCertificate = !empty($certificate);
                        @endphp
                        @if ($hasCertificate)
                        <a href="{{ route('certificate.download', ['id' => $certificate->id]) }}"
                            class="btn bg-gradient-success mb-2 mb-sm-0 me-2">
                            Unduh Sertifikat
                        </a>
                        @else
                        <button class="btn bg-gradient-info mb-2 mb-sm-0 me-2" data-bs-toggle="modal"
                            data-bs-target="#modalInputSertifikat">
                            Export Sertifikat
                        </button>
                        @endif
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center">
                        @if (request()->is('students'))
                        <div class="me-3 mb-2 mb-sm-0">
                            @php
                            $selectedAcademicYear = request()->query('academic_year', $defaultAcademicYear);
                            @endphp

                            <select id="academicYearSelect" style="width: 120px;" class="form-select" aria-label="Pilih Angkatan" onchange="window.location.href=this.value">
                                @php
                                $startYear = 2023;
                                $endYear = $currentYear + 1;
                                @endphp
                                @for ($year = $startYear; $year <= $endYear; $year++)
                                    @php
                                    $academicYear=$year . '-' . ($year + 1);
                                    @endphp
                                    <option value="{{ request()->fullUrlWithQuery(['academic_year' => $academicYear]) }}" {{ $academicYear == $selectedAcademicYear ? 'selected' : '' }}>
                                    {{ $academicYear }}
                                    </option>
                                    @endfor
                            </select>
                        </div>
                        @endif
                        <!-- Add data End -->

                        {{-- Filter Start --}}
                        @if (!empty($filter))
                        <div class="mb-2 mb-sm-0">
                            {{ $dropdown }}
                        </div>
                        @endif
                        {{-- Filter end --}}

                        <div class="card-header p-0">
                            <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                                <div class="input-group">
                                    <span class="input-group-text text-body">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control search" placeholder="Cari..." name="search" id="search-input">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table --}}
            <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive p-0">
                    <table id="table-data" class="table align-items-center mb-0">
                        <thead>
                            {{ $thead }}
                        </thead>
                        <tbody>
                            {{ $tbody }}
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                {{ $pagination ? $pagination->links() : '' }}
            </div>
        </div>
    </div>
    </div>

    @once
    @push('scripts')
    <script type="module">
        // Function to attach event handlers
        function attachEventHandlers() {
            $('.button-delete').off('click').on('click', function() {
                const buttonId = $(this).attr('id');
                utils.useDeleteButton({
                    buttonId: buttonId
                });
            });
        }

        // Initial event handler attachment
        attachEventHandlers();

        // Add event listener for search input
        $('#search-input').on('input', function() {
            const searchQuery = $(this).val();
            const url = new URL(window.location.href);
            url.searchParams.set('search', searchQuery);
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const newDoc = new DOMParser().parseFromString(html, 'text/html');
                    const newTbody = newDoc.querySelector('#table-data tbody');
                    const newPagination = newDoc.querySelector('.pagination');
                    document.querySelector('#table-data tbody').innerHTML = newTbody.innerHTML;
                    if (newPagination) {
                        document.querySelector('.pagination').innerHTML = newPagination.innerHTML;
                    }
                    // Reattach event handlers after content update
                    attachEventHandlers();
                });
        });

        $(document).ready(function() {
            $('#approve-all-presences').on('click', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');

                window.swal({
                    title: "Are you sure?",
                    text: "You are about to approve all valid presences.",
                    icon: "warning",
                    buttons: {
                        cancel: {
                            text: "Cancel",
                            value: null,
                            visible: true,
                            className: "btn btn-primary",
                            closeModal: true,
                        },
                        confirm: {
                            text: "Approve All",
                            value: true,
                            visible: true,
                            className: "btn btn-success",
                            closeModal: true,
                        },
                    },
                }).then((value) => {
                    if (value) {
                        window.location.href = url;
                    }
                });
            });

            $('#approve-all-journals').on('click', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');

                window.swal({
                    title: "Are you sure?",
                    text: "You are about to approve all valid journals.",
                    icon: "warning",
                    buttons: {
                        cancel: {
                            text: "Cancel",
                            value: null,
                            visible: true,
                            className: "btn btn-primary",
                            closeModal: true,
                        },
                        confirm: {
                            text: "Approve All",
                            value: true,
                            visible: true,
                            className: "btn btn-success",
                            closeModal: true,
                        },
                    },
                }).then((value) => {
                    if (value) {
                        window.location.href = url;
                    }
                });
            });
        });
    </script>
    @endpush
    @endonce