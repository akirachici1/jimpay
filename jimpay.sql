/*==============================================================*/
/* DBMS name:      MySQL 5.0                                    */
/* Created on:     23/04/2026 10:39:13                          */
/*==============================================================*/


drop table if exists BUKTI_PEMBAYARAN;

drop table if exists LAPORAN;

drop table if exists PEMBAYARAN;

drop table if exists PENGELUARAN;

drop table if exists PENGURUS;

drop table if exists PERIODE_IURAN;

drop table if exists VERIFIKASI;

drop table if exists WARGA;

/*==============================================================*/
/* Table: BUKTI_PEMBAYARAN                                      */
/*==============================================================*/
create table BUKTI_PEMBAYARAN
(
   ID_DOKUMEN           int not null,
   ID_PEMBAYARAN        int,
   BUKTI_PEMBAYARAN     varchar(255) not null,
   primary key (ID_DOKUMEN)
);

/*==============================================================*/
/* Table: LAPORAN                                               */
/*==============================================================*/
create table LAPORAN
(
   ID_LAPORAN           int not null,
   ID_PERIODE           int,
   TOTAL_PEMASUKAN      decimal(15,2) not null,
   JUMLAH_WARGA_LUNAS   int not null,
   JUMLAH_WARGA_PENDING int not null,
   JUMLAH_WARGA_BELUM   int not null,
   SALDO                decimal(15,2) not null,
   TANGGAL_REKAP        date,
   primary key (ID_LAPORAN)
);

/*==============================================================*/
/* Table: PEMBAYARAN                                            */
/*==============================================================*/
create table PEMBAYARAN
(
   ID_PEMBAYARAN        int not null,
   ID_PERIODE           int,
   ID_WARGA             int,
   TANGGAL_BAYAR        datetime not null,
   STATUS               varchar(50) not null,
   primary key (ID_PEMBAYARAN)
);

/*==============================================================*/
/* Table: PENGELUARAN                                           */
/*==============================================================*/
create table PENGELUARAN
(
   ID_PENGELUARAN       int not null,
   ID_PENGURUS          int,
   ID_LAPORAN           int,
   NOMINAL_P            decimal(15,2),
   KETERANGAN_P         text,
   TANGGAL_P            date,
   primary key (ID_PENGELUARAN)
);

/*==============================================================*/
/* Table: PENGURUS                                              */
/*==============================================================*/
create table PENGURUS
(
   ID_PENGURUS          int not null,
   USER_ADMIN           varchar(50) not null,
   PASSWORD_ADMIN       varchar(50) not null,
   primary key (ID_PENGURUS)
);

/*==============================================================*/
/* Table: PERIODE_IURAN                                         */
/*==============================================================*/
create table PERIODE_IURAN
(
   ID_PERIODE           int not null,
   BULAN                int not null,
   TAHUN                int not null,
   NOMINAL              decimal not null,
   TANGGAL_BATAS_BAYAR  datetime not null,
   primary key (ID_PERIODE)
);

/*==============================================================*/
/* Table: VERIFIKASI                                            */
/*==============================================================*/
create table VERIFIKASI
(
   ID_VERIFIKASI        int not null,
   ID_PENGURUS          int,
   ID_DOKUMEN           int,
   HASIL                bit not null,
   TANGGAL_KONFIRMASI   date not null,
   KETERANGAN           text,
   primary key (ID_VERIFIKASI)
);

/*==============================================================*/
/* Table: WARGA                                                 */
/*==============================================================*/
create table WARGA
(
   ID_WARGA             int not null,
   USERNAME             varchar(50) not null,
   PASSWORD             varchar(50) not null,
   NAMA                 varchar(50) not null,
   GANG                 varchar(10) not null,
   primary key (ID_WARGA)
);

alter table BUKTI_PEMBAYARAN add constraint FK_TERLAMPIR2 foreign key (ID_PEMBAYARAN)
      references PEMBAYARAN (ID_PEMBAYARAN);

alter table LAPORAN add constraint FK_MENGHASILKAN2 foreign key (ID_PERIODE)
      references PERIODE_IURAN (ID_PERIODE);

alter table PEMBAYARAN add constraint FK_MELAKUKAN foreign key (ID_WARGA)
      references WARGA (ID_WARGA);

alter table PEMBAYARAN add constraint FK_MENCAKUP foreign key (ID_PERIODE)
      references PERIODE_IURAN (ID_PERIODE);

alter table PENGELUARAN add constraint FK_DICATAT foreign key (ID_LAPORAN)
      references LAPORAN (ID_LAPORAN);

alter table PENGELUARAN add constraint FK_MENCATAT foreign key (ID_PENGURUS)
      references PENGURUS (ID_PENGURUS);

alter table VERIFIKASI add constraint FK_MENDUKUNG2 foreign key (ID_DOKUMEN)
      references BUKTI_PEMBAYARAN (ID_DOKUMEN);

alter table VERIFIKASI add constraint FK_VERIFIKASI foreign key (ID_PENGURUS)
      references PENGURUS (ID_PENGURUS);

