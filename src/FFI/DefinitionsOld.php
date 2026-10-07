<?php

namespace Sciter\FFI;

final class DefinitionsOld
{
    public static function cdef(): string
    {
        return <<<'C'
/*
|--------------------------------------------------------------------------
| Basic types
|--------------------------------------------------------------------------
*/

typedef unsigned int UINT;
typedef unsigned char BYTE;
typedef unsigned long UINT_PTR;
typedef long INT_PTR;
typedef int SBOOL;

typedef void* LPVOID;
typedef void* HWINDOW;

typedef struct tagRECT {
    int left;
    int top;
    int right;
    int bottom;
} RECT, *LPRECT;

typedef void (*SCITER_UNUSED_FN)(void);


/*
|--------------------------------------------------------------------------
| SOM / VALUE types
|--------------------------------------------------------------------------
|
| THIS is what I meant by "before ISciterAPI".
|
*/

typedef unsigned long long UINT64;
typedef unsigned long size_t;

typedef struct VALUE {
    UINT   t;
    UINT   u;
    UINT64 d;
} VALUE;

typedef VALUE SCITER_VALUE;



typedef unsigned short WCHAR16;



typedef UINT64 som_atom_t;

typedef struct som_asset_t som_asset_t;
typedef struct som_asset_class_t som_asset_class_t;
typedef struct som_passport_t som_passport_t;

typedef long (*som_add_ref_t)(som_asset_t* thing);
typedef long (*som_release_t)(som_asset_t* thing);

typedef long (*som_get_interface_t)(
    som_asset_t* thing,
    const char* name,
    void** out
);

typedef som_passport_t* (*som_get_passport_t)(
    som_asset_t* thing
);

struct som_asset_class_t {
    som_add_ref_t asset_add_ref;
    som_release_t asset_release;
    som_get_interface_t asset_get_interface;
    som_get_passport_t asset_get_passport;
};

struct som_asset_t {
    som_asset_class_t* isa;
};

typedef SBOOL (*som_method_t)(
    som_asset_t* thing,
    UINT argc,
    const SCITER_VALUE* argv,
    SCITER_VALUE* result
);

typedef struct som_method_def_t {
    void* reserved;
    som_atom_t name;
    size_t params;
    som_method_t func;
} som_method_def_t;

struct som_passport_t {
    UINT64 flags;
    som_atom_t name;

    void* properties;
    size_t n_properties;

    const som_method_def_t* methods;
    size_t n_methods;

    void* item_getter;
    void* item_setter;
    void* item_next;
    void* prop_getter;
    void* prop_setter;
    void* name_resolver;
    void* reserved;
};







typedef struct _ISciterAPI {

    UINT version;

    /*
     * slots 0..29
     */
    SCITER_UNUSED_FN SciterClassName;          /* 0 */
    UINT (*SciterVersion)(UINT n);             /* 1 */

    SCITER_UNUSED_FN api_02_05[4];             /* 2..5 */

    SBOOL (*SciterLoadFile)(
        HWINDOW hwnd,
        const void* filename
    );                                          /* 6 */

    SBOOL (*SciterLoadHtml)(
        HWINDOW hwnd,
        const BYTE* html,
        UINT htmlSize,
        const void* baseUrl
    );                                          /* 7 */

    SCITER_UNUSED_FN api_08_29[22];             /* 8..29 */

    HWINDOW (*SciterCreateWindow)(
        UINT creationFlags,
        LPRECT frame,
        LPVOID reserved1,
        LPVOID reserved2,
        HWINDOW parent
    );                                          /* 30 */



    




    /*
    * Slots 31..123
    *
    * DOM APIs we haven't mapped yet.
    */
    SCITER_UNUSED_FN api_31_123[93];


    /*
    |--------------------------------------------------------------------------
    | VALUE API
    |--------------------------------------------------------------------------
    |
    | Slots 124..151
    |
    */

    UINT (*ValueInit)(
        VALUE* pval
    );

    UINT (*ValueClear)(
        VALUE* pval
    );

    UINT (*ValueCompare)(
        const VALUE* pval1,
        const VALUE* pval2
    );

    UINT (*ValueCopy)(
        VALUE* pdst,
        const VALUE* psrc
    );

    UINT (*ValueIsolate)(
        VALUE* pdst
    );

    UINT (*ValueType)(
        const VALUE* pval,
        UINT* pType,
        UINT* pUnits
    );

    UINT (*ValueStringData)(
        const VALUE* pval,
        const WCHAR16** pChars,
        UINT* pNumChars
    );

    UINT (*ValueStringDataSet)(
        VALUE* pval,
        const WCHAR16* chars,
        UINT numChars,
        UINT units
    );

    /*
    * Remaining VALUE API:
    * slots 132..151
    */
    SCITER_UNUSED_FN api_value_132_151[20];


    /*
    * Former script VM reserved entries:
    * slots 152..155
    */
    SCITER_UNUSED_FN api_reserved_152_155[4];


    /*
    * Slots 156..171:
    *
    * Archive API
    * FireEvent
    * callback API
    * graphics/request APIs
    * DirectX placeholders
    * SciterProcX
    */
    SCITER_UNUSED_FN api_156_171[16];


    /*
    * slot 172
    */
    UINT64 (*SciterAtomValue)(
        const char* name
    );

    /*
    * slot 173
    */
    SBOOL (*SciterAtomNameCB)(
        UINT64 atomv,
        void* rcv,
        LPVOID rcv_param
    );

    /*
    * slot 174
    */
    SBOOL (*SciterSetGlobalAsset)(
        som_asset_t* asset
    );

    /*
    * slots 175..182
    */
    SCITER_UNUSED_FN api_175_182[8];

    /*
    * slot 183
    */
    INT_PTR (*SciterExec)(
        UINT appCmd,
        UINT_PTR p1,
        UINT_PTR p2
    );

    INT_PTR (*SciterWindowExec)(
        HWINDOW hwnd,
        UINT windowCmd,
        UINT_PTR p1,
        UINT_PTR p2
    );

} ISciterAPI;

const ISciterAPI* SciterAPI(void);
C;
    }
}
