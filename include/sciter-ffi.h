/*
|--------------------------------------------------------------------------
| Primitive types
|--------------------------------------------------------------------------
*/

typedef unsigned int       UINT;
typedef unsigned char      BYTE;
typedef unsigned long      UINT_PTR;
typedef long               INT_PTR;
typedef int                SBOOL;

typedef unsigned long long UINT64;
typedef unsigned long      size_t;
typedef signed int int32_t;
typedef double FLOAT_VALUE;

typedef void* LPVOID;
typedef void* HWINDOW;

typedef void* HELEMENT;
typedef void* HNODE;
typedef int SCDOM_RESULT;


/*
|--------------------------------------------------------------------------
| Geometry
|--------------------------------------------------------------------------
*/

typedef struct tagRECT {
    int left;
    int top;
    int right;
    int bottom;
} RECT, *LPRECT;


/*
|--------------------------------------------------------------------------
| Placeholder API function
|--------------------------------------------------------------------------
|
| Used only to preserve the exact ISciterAPI table layout for APIs that
| php-sciter does not expose yet.
|
*/

typedef void (*SCITER_UNUSED_FN)(void);

// Sciter notification structure
typedef struct SCITER_CALLBACK_NOTIFICATION {
    UINT code;
    HWINDOW hwnd;
} SCITER_CALLBACK_NOTIFICATION;

// Host callback type
typedef UINT (*SciterHostCallback)(
    SCITER_CALLBACK_NOTIFICATION* notification,
    void* callbackParam
);

typedef struct SCN_POSTED_NOTIFICATION {
    UINT code;
    HWINDOW hwnd;
    UINT_PTR wparam;
    UINT_PTR lparam;
    UINT_PTR lreturn;
} SCN_POSTED_NOTIFICATION;


/*
|--------------------------------------------------------------------------
| VALUE
|--------------------------------------------------------------------------
|
| Matches include/value.h:
|
| typedef struct {
|     UINT   t;
|     UINT   u;
|     UINT64 d;
| } VALUE;
|
| We give the struct a tag to make PHP FFI type handling less ambiguous.
|
*/

typedef struct VALUE {
    UINT   t;
    UINT   u;
    UINT64 d;
} VALUE;

typedef VALUE SCITER_VALUE;


/*
|--------------------------------------------------------------------------
| UTF-16
|--------------------------------------------------------------------------
*/

typedef unsigned short WCHAR16;
typedef unsigned short uint16_t;


/*
|--------------------------------------------------------------------------
| Sciter Object Model (SOM)
|--------------------------------------------------------------------------
*/

typedef UINT64 som_atom_t;

typedef struct som_asset_t       som_asset_t;
typedef struct som_asset_class_t som_asset_class_t;
typedef struct som_passport_t    som_passport_t;


/*
| Asset reference management
*/

typedef long (*som_add_ref_t)(
    som_asset_t* thing
);

typedef long (*som_release_t)(
    som_asset_t* thing
);

typedef long (*som_get_interface_t)(
    som_asset_t* thing,
    const char* name,
    void** out
);

typedef som_passport_t* (*som_get_passport_t)(
    som_asset_t* thing
);


/*
| Asset class
*/

struct som_asset_class_t {
    som_add_ref_t       asset_add_ref;
    som_release_t       asset_release;
    som_get_interface_t asset_get_interface;
    som_get_passport_t  asset_get_passport;
};


/*
| Asset
*/

struct som_asset_t {
    som_asset_class_t* isa;
};


/*
| Method callback
|
| Matches sciter-om-def.h:
|
| typedef SBOOL(*som_method_t)(
|     som_asset_t* thing,
|     UINT argc,
|     const SOM_VALUE* argv,
|     SOM_VALUE* p_result
| );
|
*/

typedef SBOOL (*som_method_t)(
    som_asset_t* thing,
    UINT argc,
    const SCITER_VALUE* argv,
    SCITER_VALUE* result
);


/*
| Method definition
*/

typedef struct som_method_def_t {
    void*        reserved;
    som_atom_t   name;
    size_t       params;
    som_method_t func;
} som_method_def_t;


/*
| Passport
*/

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

typedef int (*KeyValueCallback)(
    void* param,
    const VALUE* pkey,
    const VALUE* pval
);


/*
|--------------------------------------------------------------------------
| ISciterAPI
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| The positions below were verified against the actual Sciter SDK using:
|
|     offsetof(ISciterAPI, member)
|
| on a 64-bit macOS build where:
|
|     sizeof(void*)      = 8
|     sizeof(ISciterAPI) = 1512
|
| Known verified slots:
|
|   SciterClassName        0
|   SciterVersion          1
|   SciterLoadFile         6
|   SciterLoadHtml         7
|   SciterCreateWindow    30
|
|   ValueInit            128
|   ValueClear           129
|   ValueType            133
|   ValueStringData      134
|   ValueStringDataSet   135
|   ValueIntData         136
|   ValueIsNativeFunctor 155
|
|   SciterAtomValue      172
|   SciterAtomNameCB     173
|   SciterSetGlobalAsset 174
|
|   SciterExec           183
|   SciterWindowExec     184
|
*/

typedef struct _ISciterAPI {

    /*
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    */

    UINT version;


    /*
    |--------------------------------------------------------------------------
    | Core API
    |--------------------------------------------------------------------------
    |
    | slot 0
    */

    SCITER_UNUSED_FN SciterClassName;


    /*
     * slot 1
     */

    UINT (*SciterVersion)(
        UINT n
    );


    /*
     * slots 2..5
     */

    SCITER_UNUSED_FN api_02_05[4];


    /*
     * slot 6
     */

    SBOOL (*SciterLoadFile)(
        HWINDOW hwnd,
        const void* filename
    );


    /*
     * slot 7
     */

    SBOOL (*SciterLoadHtml)(
        HWINDOW hwnd,
        const BYTE* html,
        UINT htmlSize,
        const void* baseUrl
    );

    /*
     * slot 8
     */
    void (*SciterSetCallback)(
        HWINDOW hwnd,
        SciterHostCallback callback,
        void* callbackParam
    );

    /*
     * slot 9
     */
    SBOOL (*SciterSetMasterCSS)(
        const unsigned char* utf8,
        UINT numBytes
    );


    /*
     * slots 10..29
     */

    SCITER_UNUSED_FN api_10_29[20];


    /*
    |--------------------------------------------------------------------------
    | Window creation
    |--------------------------------------------------------------------------
    |
    | slot 30
    */

    HWINDOW (*SciterCreateWindow)(
        UINT creationFlags,
        LPRECT frame,
        LPVOID reserved1,
        LPVOID reserved2,
        HWINDOW parent
    );


    /*
    |--------------------------------------------------------------------------
    | Debug + DOM Element + DOM Node APIs
    |--------------------------------------------------------------------------
    |
    | slots 31..127
    |
    | ValueInit was measured at slot 128, therefore:
    |
    |     128 - 31 = 97 entries
    |
    */

    SCITER_UNUSED_FN api_31_127[97];


    /*
    |--------------------------------------------------------------------------
    | VALUE API
    |--------------------------------------------------------------------------
    */


    /*
     * slot 128
     */

    UINT (*ValueInit)(
        VALUE* pval
    );


    /*
     * slot 129
     */

    UINT (*ValueClear)(
        VALUE* pval
    );


    /*
     * slot 130
     */

    UINT (*ValueCompare)(
        const VALUE* pval1,
        const VALUE* pval2
    );


    /*
     * slot 131
     */

    UINT (*ValueCopy)(
        VALUE* pdst,
        const VALUE* psrc
    );


    /*
     * slot 132
     */

    UINT (*ValueIsolate)(
        VALUE* pdst
    );


    /*
     * slot 133
     */

    UINT (*ValueType)(
        const VALUE* pval,
        UINT* pType,
        UINT* pUnits
    );


    /*
     * slot 134
     */

    UINT (*ValueStringData)(
        const VALUE* pval,
        const WCHAR16** pChars,
        UINT* pNumChars
    );


    /*
     * slot 135
     */

    UINT (*ValueStringDataSet)(
        VALUE* pval,
        const WCHAR16* chars,
        UINT numChars,
        UINT units
    );


    /*
     * slots 136..155
     *
     * ValueIntData
     * ValueIntDataSet
     * ValueInt64Data
     * ValueInt64DataSet
     * ValueFloatData
     * ValueFloatDataSet
     * ValueBinaryData
     * ValueBinaryDataSet
     * ValueElementsCount
     * ValueNthElementValue
     * ValueNthElementValueSet
     * ValueNthElementKey
     * ValueEnumElements
     * ValueSetValueToKey
     * ValueGetValueOfKey
     * ValueToString
     * ValueFromString
     * ValueInvoke
     * ValueNativeFunctorSet
     * ValueIsNativeFunctor
     */

    UINT (*ValueIntData)(
        const VALUE* pval,
        int32_t* pData
    );

    UINT (*ValueIntDataSet)(
        VALUE* pval,
        int32_t data,
        UINT type,
        UINT units
    );

    /*
    * Preserve the two Int64 function-pointer slots.
    * We'll implement Int64 support later.
    */
    SCITER_UNUSED_FN api_value_int64[2];

    UINT (*ValueFloatData)(
        const VALUE* pval,
        double* pData
    );

    UINT (*ValueFloatDataSet)(
        VALUE* pval,
        double data,
        UINT type,
        UINT units
    );

    SCITER_UNUSED_FN api_value_142_143[2];

    UINT (*ValueElementsCount)(
        const VALUE* pval,
        int32_t* pn
    );

    UINT (*ValueNthElementValue)(
        const VALUE* pval,
        int32_t n,
        VALUE* pretval
    );

    UINT (*ValueNthElementValueSet)(
        VALUE* pval,
        int32_t n,
        const VALUE* pval_to_set
    );

    UINT (*ValueNthElementKey)(
        const VALUE* pval,
        int32_t n,
        VALUE* pretval
    );

    /* Slot 148 */
    UINT (*ValueEnumElements)(
        const VALUE* pval,
        KeyValueCallback* penum,
        void* param
    );

    /* Slot 149 */
    UINT (*ValueSetValueToKey)(
        VALUE* pval,
        const VALUE* pkey,
        const VALUE* pval_to_set
    );

    SCITER_UNUSED_FN api_value_150_155[6];

    /*
    |--------------------------------------------------------------------------
    | Former script VM reserved entries
    |--------------------------------------------------------------------------
    |
    | slots 156..159
    */

    LPVOID reserved1;
    LPVOID reserved2;
    LPVOID reserved3;
    LPVOID reserved4;


    /*
    |--------------------------------------------------------------------------
    | Archive / Event / Graphics / Platform APIs
    |--------------------------------------------------------------------------
    |
    | slots 160..171
    |
    | SciterOpenArchive
    | SciterGetArchiveItem
    | SciterCloseArchive
    | SciterFireEvent
    | SciterGetCallbackParam
    | SciterPostCallback
    | GetSciterGraphicsAPI
    | GetSciterRequestAPI
    | SciterCreateOnDirectXWindow
    | SciterRenderOnDirectXWindow
    | SciterRenderOnDirectXTexture
    | SciterProcX
    |
    */

    /*
    * Archive APIs
    * Native slots 161..163
    */
    SCITER_UNUSED_FN archive_api[3];

    /*
    * Event API
    * Native slot 164
    */
    SCITER_UNUSED_FN fire_event_api;

    /*
    * Host callback APIs
    * Native slots 165..166
    */
    void* (*SciterGetCallbackParam)(HWINDOW hwnd);

    UINT_PTR (*SciterPostCallback)(
        HWINDOW hwnd,
        UINT_PTR wparam,
        UINT_PTR lparam,
        UINT timeoutms
    );

    /*
    * Graphics, DirectX and platform APIs
    * Native slots 167..172
    */
    SCITER_UNUSED_FN api_167_172[6];


    /*
    |--------------------------------------------------------------------------
    | SOM / Atom API
    |--------------------------------------------------------------------------
    */


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
     *
     * SciterGetElementAsset
     * SciterSetVariable
     * SciterGetVariable
     * SciterElementUnwrap
     * SciterElementWrap
     * SciterNodeUnwrap
     * SciterNodeWrap
     * SciterReleaseGlobalAsset
     */

    /*
     * Slots 175..181
     */
    SCITER_UNUSED_FN api_175_181[7];

    /*
     * Slot 182
     */
    SBOOL (*SciterReleaseGlobalAsset)(
        som_asset_t* asset
    );


    /*
    |--------------------------------------------------------------------------
    | Application execution
    |--------------------------------------------------------------------------
    */


    /*
     * slot 183
     */

    INT_PTR (*SciterExec)(
        UINT appCmd,
        UINT_PTR p1,
        UINT_PTR p2
    );


    /*
     * slot 184
     */

    INT_PTR (*SciterWindowExec)(
        HWINDOW hwnd,
        UINT windowCmd,
        UINT_PTR p1,
        UINT_PTR p2
    );

    /*
    * EGL / Animation / Paint APIs
    * Native slots 186..189
    */

    void* (*SciterEGLGetProcAddress)(
        const char* procName
    );

    SCDOM_RESULT (*SciterEGLSendEvent)(
        HELEMENT he,
        UINT eventCode,
        UINT_PTR reason
    );

    SCDOM_RESULT (*SciterRequestAnimationFrameEvent)(
        HELEMENT he,
        UINT eventCode,
        UINT_PTR reason
    );

    SCDOM_RESULT (*SciterRequestPaint)(
        HELEMENT he
    );

} ISciterAPI;


/*
|--------------------------------------------------------------------------
| Sciter entry point
|--------------------------------------------------------------------------
*/

const ISciterAPI* SciterAPI(void);
