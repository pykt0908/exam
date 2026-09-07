@extends('adminlte::page')

@section('title', 'ระบบบริหารจัดการข้อสอบออนไลน์')

@section('content')
<div class="fb-profile-container pt-3 pb-4">

    {{-- Facebook Profile Header Card (Cover + Avatar + Bio + Tabs) --}}
    <div class="card shadow-sm border-0 fb-header-card mb-4"
        style="border-radius: 12px; overflow: hidden; background: #ffffff;">

        {{-- 1. Cover Photo --}}
        <div class="fb-cover-photo-wrapper">
            <img src="{{ asset('images/bg.jpg') }}" alt="ภาพหน้าปก วิทยาลัยเทคโนโลยีศรีราชา" class="fb-cover-photo">
            <div class="fb-cover-overlay"></div>
        </div>

        {{-- 2. Profile Info Area --}}
        <div class="fb-profile-body px-3 px-md-4 pb-3 pb-md-4 mt-3">
            <div
                class="d-flex flex-column flex-md-row align-items-center align-items-md-end justify-content-between fb-profile-row">

                {{-- Avatar + Name + Meta --}}
                <div
                    class="d-flex flex-column flex-md-row align-items-center align-items-md-end text-center text-md-left">

                    {{-- Circular Avatar Overlapping Cover --}}
                    <div class="fb-avatar-container">
                        <div class="fb-avatar-circle">
                            <img src="{{ asset('images/logo.png') }}" alt="โลโก้วิทยาลัยเทคโนโลยีศรีราชา"
                                class="fb-avatar-img">
                        </div>
                    </div>

                    {{-- Title & Bio --}}
                    <div class="fb-name-meta ml-md-4 mt-3 mt-md-0 pb-md-2">
                        <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                            <h2 class="font-weight-bold text-dark mb-1 mt-2 fb-profile-title"
                                style="font-size: 1.85rem; letter-spacing: -0.4px;">
                                ระบบบริหารจัดการข้อสอบออนไลน์ วิทยาลัยเทคโนโลยีศรีราชา
                            </h2>
                            <i class="fas fa-check-circle text-primary ml-2 fb-verified-badge"
                                title="ระบบที่ผ่านการรับรอง" style="font-size: 1.35rem;"></i>
                        </div>
                        <div class="text-muted font-weight-bold text-sm mb-1">
                            สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล &bull; งานส่งเสริมการวิจัย นวัตกรรม และสิ่งประดิษฐ์ &bull;
                            วิทยาลัยเทคโนโลยีศรีราชา
                        </div>
                    </div>

                </div>

                {{-- Action Buttons on Right --}}
                <div class="fb-actions mt-3 mt-md-0 pb-md-2 d-flex flex-wrap justify-content-center">
                    <a href="{{ asset('docs/manual_teacher.pdf') }}" target="_blank"
                        class="btn btn-primary px-3 py-2 mr-2 font-weight-bold shadow-sm d-inline-flex align-items-center mb-1"
                        style="border-radius: 8px; font-size: 0.95rem;">
                        <i class="fas fa-chalkboard-teacher mr-2"></i> คู่มือการใช้งานสำหรับครู
                    </a>
                    <a href="{{ asset('docs/manual_student.pdf') }}" target="_blank"
                        class="btn px-3 py-2 font-weight-bold shadow-sm d-inline-flex align-items-center mb-1"
                        style="border-radius: 8px; background: #e4e6eb; color: #050505; font-size: 0.95rem;">
                        <i class="fas fa-user-graduate mr-2 text-dark"></i> คู่มือสำหรับนักเรียน
                    </a>
                </div>

            </div>

        </div>
    </div>

    {{-- Empty Content Card --}}
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0" style="border-radius: 12px; min-height: 200px;">
                <div class="card-body p-5">
                    {{-- ใส่เนื้อหาตรงนี้ --}}
                    <p class="text-center text-muted">-- อยู่ในช่วงอัปเดตข้อมูล --</p>
                </div>
            </div>
        </div>
    </div>

</div>
@stop

@section('css')
<style>
    /* ==========================================================================
   Facebook Profile Page Style Layout for Admin Dashboard
   ========================================================================== */

    .fb-profile-container {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0;
    }

    /* 1. Cover Photo */
    .fb-cover-photo-wrapper {
        position: relative;
        width: 100%;
        height: 350px;
        background: #0d233a;
        overflow: hidden;
    }

    .fb-cover-photo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center 48%;
        transition: transform 0.4s ease;
    }

    .fb-cover-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0, 0, 0, 0.15) 0%, rgba(0, 0, 0, 0.45) 100%);
    }

    .fb-cover-badge {
        position: absolute;
        top: 18px;
        right: 20px;
        z-index: 5;
    }

    /* 2. Avatar Overlap */
    .fb-avatar-container {
        position: relative;
        margin-top: -85px;
        z-index: 10;
    }

    .fb-avatar-circle {
        width: 160px;
        height: 160px;
        border-radius: 50%;
        background: #ffffff;
        border: 5px solid #ffffff;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.18);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition: transform 0.25s ease;
    }

    .fb-avatar-circle:hover {
        transform: scale(1.02);
    }

    .fb-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 12px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .fb-cover-photo-wrapper {
            height: 200px;
        }

        .fb-cover-photo {
            object-position: center 48%;
        }

        .fb-avatar-circle {
            width: 120px;
            height: 120px;
            border-width: 4px;
        }

        .fb-avatar-container {
            margin-top: -60px;
        }

        .fb-profile-title {
            font-size: 1.4rem !important;
        }
    }
</style>
@stop